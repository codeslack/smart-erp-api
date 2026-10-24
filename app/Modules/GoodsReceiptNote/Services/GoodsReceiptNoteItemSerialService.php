<?php

namespace App\Modules\GoodsReceiptNote\Services;

use Illuminate\Support\Facades\DB;

use App\Core\Exceptions\BusinessException;

use App\Modules\GoodsReceiptNote\Enums\GoodsReceiptNoteStatusEnum;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItem;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItemSerial;
use App\Modules\GoodsReceiptNote\Repositories\Contracts\GoodsReceiptNoteItemSerialRepositoryInterface;
use App\Modules\GoodsReceiptNote\Rules\GoodsReceiptNoteRules;

class GoodsReceiptNoteItemSerialService
{
    public function __construct(
        protected GoodsReceiptNoteItemSerialRepositoryInterface $serials,
        protected GoodsReceiptNoteRules $rules,
    ) {}

    public function create(
        GoodsReceiptNoteItem $item,
        array $data
    ): GoodsReceiptNoteItemSerial {

        return DB::transaction(function () use ($item, $data) {

            $this->ensureDraftItem($item);

            $serialNo = trim(
                (string) ($data['serial_number'] ?? '')
            );

            if ($serialNo === '') {
                throw new BusinessException(
                    'Serial number is required.'
                );
            }

            /*
             * Every serial represents exactly one base unit.
             */
            $this->rules->ensureWholeQuantity(
                (float) $item->base_quantity
            );

            $this->ensureSerialNotExists(
                $serialNo
            );

            $this->ensureSerialCountDoesNotExceedItem(
                $item
            );

            $serial = $this->serials->create([
                'goods_receipt_note_item_id' =>
                    $item->id,

                'serial_number' => $serialNo,

                'created_by' => userId(),
                'updated_by' => userId(),
            ]);

            return $serial->refresh();
        });
    }

    public function update(
        GoodsReceiptNoteItemSerial $serial,
        array $data
    ): GoodsReceiptNoteItemSerial {

        return DB::transaction(function () use ($serial, $data) {

            $item = $serial->goodsReceiptNoteItem;

            if (! $item) {
                throw new BusinessException(
                    'Goods receipt note item not found.'
                );
            }

            $this->ensureDraftItem($item);

            $serialNo = trim(
                (string) (
                    $data['serial_number']
                    ?? $serial->serial_number
                )
            );

            if ($serialNo === '') {
                throw new BusinessException(
                    'Serial number is required.'
                );
            }

            $this->ensureSerialNotExists(
                $serialNo,
                $serial->id
            );

            $this->serials->update($serial, [
                'serial_number' => $serialNo,
                'updated_by' => userId(),
            ]);

            return $serial->refresh();
        });
    }

    public function delete(
        GoodsReceiptNoteItemSerial $serial
    ): void {

        $item = $serial->goodsReceiptNoteItem;

        if (! $item) {
            throw new BusinessException(
                'Goods receipt note item not found.'
            );
        }

        $this->ensureDraftItem($item);

        $this->serials->delete($serial);
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
                'Serial details can only be changed while the GRN is in draft status.'
            );
        }
    }

    protected function ensureSerialNotExists(
        string $serialNo,
        ?int $ignoreId = null
    ): void {

        if (
            $this->serials->existsSerialNo(
                $serialNo,
                $ignoreId
            )
        ) {
            throw new BusinessException(
                "Serial number '{$serialNo}' already exists."
            );
        }
    }

    protected function ensureSerialCountDoesNotExceedItem(
        GoodsReceiptNoteItem $item
    ): void {

        $serialCount = $item->serials->count();

        $baseQuantity = (float) $item->base_quantity;

        if ($serialCount >= (int) $baseQuantity) {
            throw new BusinessException(
                'Serial count cannot exceed the GRN item base quantity.'
            );
        }
    }
}