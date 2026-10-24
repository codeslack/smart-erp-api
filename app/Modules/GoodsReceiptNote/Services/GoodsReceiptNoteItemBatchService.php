<?php

namespace App\Modules\GoodsReceiptNote\Services;

use Illuminate\Support\Facades\DB;

use App\Core\Exceptions\BusinessException;

use App\Modules\GoodsReceiptNote\Enums\GoodsReceiptNoteStatusEnum;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItem;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItemBatch;
use App\Modules\GoodsReceiptNote\Repositories\Contracts\GoodsReceiptNoteItemBatchRepositoryInterface;
use App\Modules\GoodsReceiptNote\Repositories\Contracts\GoodsReceiptNoteItemRepositoryInterface;
use App\Modules\GoodsReceiptNote\Rules\GoodsReceiptNoteRules;

class GoodsReceiptNoteItemBatchService
{
    public function __construct(
        protected GoodsReceiptNoteItemBatchRepositoryInterface $batches,
        protected GoodsReceiptNoteItemRepositoryInterface $items,
        protected GoodsReceiptNoteRules $rules,
    ) {}

    public function create(
        GoodsReceiptNoteItem $item,
        array $data
    ): GoodsReceiptNoteItemBatch {

        return DB::transaction(function () use ($item, $data) {

            $this->ensureDraftItem($item);

            $batchNo = trim(
                (string) ($data['batch_no'] ?? '')
            );

            if ($batchNo === '') {
                throw new BusinessException(
                    'Batch number is required.'
                );
            }

            $quantity = (float) (
                $data['quantity'] ?? 0
            );

            $this->rules->ensurePositiveQuantity(
                $quantity
            );

            $this->ensureBatchNotExists(
                $item,
                $batchNo
            );

            $this->ensureQuantityDoesNotExceedItem(
                $item,
                $quantity
            );

            $batch = $this->batches->create([
                'goods_receipt_note_item_id' =>
                    $item->id,

                'batch_no' => $batchNo,

                /*
                 * Batch quantity is always stored
                 * in base/inventory units.
                 */
                'quantity' => $quantity,

                'expiry_date' =>
                    $data['expiry_date'] ?? null,

                'created_by' => userId(),
                'updated_by' => userId(),
            ]);

            return $batch->refresh();
        });
    }

    public function update(
        GoodsReceiptNoteItemBatch $batch,
        array $data
    ): GoodsReceiptNoteItemBatch {

        return DB::transaction(function () use ($batch, $data) {

            $item = $batch->goodsReceiptNoteItem;

            if (! $item) {
                throw new BusinessException(
                    'Goods receipt note item not found.'
                );
            }

            $this->ensureDraftItem($item);

            $batchNo = trim(
                (string) (
                    $data['batch_no']
                    ?? $batch->batch_no
                )
            );

            $quantity = (float) (
                $data['quantity']
                ?? $batch->quantity
            );

            if ($batchNo === '') {
                throw new BusinessException(
                    'Batch number is required.'
                );
            }

            $this->rules->ensurePositiveQuantity(
                $quantity
            );

            $this->ensureBatchNotExists(
                $item,
                $batchNo,
                $batch->id
            );

            $this->ensureQuantityDoesNotExceedItem(
                $item,
                $quantity,
                $batch->id
            );

            $this->batches->update($batch, [
                'batch_no' => $batchNo,
                'quantity' => $quantity,
                'expiry_date' =>
                    $data['expiry_date']
                    ?? $batch->expiry_date,
                'updated_by' => userId(),
            ]);

            return $batch->refresh();
        });
    }

    public function delete(
        GoodsReceiptNoteItemBatch $batch
    ): void {

        $item = $batch->goodsReceiptNoteItem;

        if (! $item) {
            throw new BusinessException(
                'Goods receipt note item not found.'
            );
        }

        $this->ensureDraftItem($item);

        $this->batches->delete($batch);
    }

    protected function ensureDraftItem(
        GoodsReceiptNoteItem $item
    ): void {

        $grn = $item->goodsReceiptNote;

        if (! $grn) {
            throw new BusinessException(
                'Goods receipt note not found.'
            );
        }

        if (
            $grn->status !==
            GoodsReceiptNoteStatusEnum::DRAFT
        ) {
            throw new BusinessException(
                'Batch details can only be changed while the GRN is in draft status.'
            );
        }
    }

    protected function ensureBatchNotExists(
        GoodsReceiptNoteItem $item,
        string $batchNo,
        ?int $ignoreId = null
    ): void {

        if (
            $this->batches->existsBatchNo(
                $item->id,
                $batchNo,
                $ignoreId
            )
        ) {
            throw new BusinessException(
                "Batch number '{$batchNo}' already exists for this GRN item."
            );
        }
    }

    protected function ensureQuantityDoesNotExceedItem(
        GoodsReceiptNoteItem $item,
        float $quantity,
        ?int $ignoreBatchId = null
    ): void {

        $existingQuantity = $item->batches
            ->when(
                $ignoreBatchId !== null,
                fn ($batches) =>
                    $batches->where(
                        'id',
                        '!=',
                        $ignoreBatchId
                    )
            )
            ->sum(
                fn ($batch) =>
                    (float) $batch->quantity
            );

        $totalQuantity =
            round(
                $existingQuantity + $quantity,
                4
            );

        $itemQuantity =
            round(
                (float) $item->base_quantity,
                4
            );

        if ($totalQuantity > $itemQuantity) {
            throw new BusinessException(
                'Total batch quantity cannot exceed the GRN item base quantity.'
            );
        }
    }
}