<?php

namespace App\Modules\GoodsReceiptNote\Services\Inventory;

use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItem;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItemBatch;

use App\Modules\Inventory\Enums\ProductBatchStatusEnum;
use App\Modules\Inventory\Models\ProductBatch;
use App\Modules\Inventory\Services\InventoryBatchService;

class GoodsReceiptNoteInventoryBatchService
{
    public function __construct(
        protected InventoryBatchService $inventoryBatchService,
    ) {}

    /**
     * Find an existing batch or create and activate a new batch.
     *
     * If another transaction creates the same batch concurrently,
     * recover from that specific unique-key violation and reuse it.
     *
     * Batch quantity is maintained by the inventory stock-in process.
     */
    public function resolve(
        GoodsReceiptNote $grn,
        GoodsReceiptNoteItem $item,
        GoodsReceiptNoteItemBatch $grnBatch,
        float $baseUnitCost,
    ): ProductBatch {
        $batchNumber = trim((string) $grnBatch->batch_no);

        $productId = (int) $item->product_id;
        $warehouseId = (int) $item->warehouse_id;

        $productVariantId = $item->product_variant_id !== null
            ? (int) $item->product_variant_id
            : null;

        $batch = $this->inventoryBatchService->findByBatchNumber(
            $batchNumber,
            $productId,
            $warehouseId,
            $productVariantId,
        );

        if ($batch !== null) {
            return $this->resolveExistingBatch($batch, $batchNumber);
        }

        try {
            $batch = $this->inventoryBatchService->createDraft([
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
                'warehouse_id' => $warehouseId,
                'batch_no' => $batchNumber,
                'expiry_date' => $grnBatch->expiry_date,
                'received_at' => $grn->grn_date,
                'unit_cost' => $baseUnitCost,
                'sourceable_type' => GoodsReceiptNote::class,
                'sourceable_id' => (int) $grn->getKey(),
                'remarks' => 'GRN ' . $grn->grn_number,
            ]);
        } catch (QueryException $exception) {
            if (
                ! $this->inventoryBatchService
                    ->isDuplicateBatchException($exception)
            ) {
                throw $exception;
            }

            /*
             * Another transaction may have inserted this batch
             * while this GRN was preparing to create it.
             *
             * Re-query through the service so product variant
             * compatibility is validated as well.
             */
            $batch = $this->inventoryBatchService->findByBatchNumberForUpdate(
                $batchNumber,
                $productId,
                $warehouseId,
                $productVariantId,
            );

            if ($batch === null) {
                // Do not hide the original database exception.
                throw $exception;
            }

            return $this->resolveExistingBatch(
                $batch,
                $batchNumber,
            );
        }

        $this->inventoryBatchService->initializeOriginalQuantity(
            $batch,
            (float) $grnBatch->quantity,
        );

        $this->inventoryBatchService->activate($batch);

        // Inventory stock-in updates remaining_quantity.

        return $batch;
    }

    /**
     * Lock and validate a batch before using it for stock-in.
     */
    protected function resolveExistingBatch(
        ProductBatch $batch,
        string $batchNumber,
    ): ProductBatch {
        $batch = $this->inventoryBatchService->lock(
            (int) $batch->getKey()
        );

        if ($batch->status !== ProductBatchStatusEnum::ACTIVE) {
            throw ValidationException::withMessages([
                'batches' => [
                    "Batch {$batchNumber} exists but is not active.",
                ],
            ]);
        }

        return $batch;
    }
}