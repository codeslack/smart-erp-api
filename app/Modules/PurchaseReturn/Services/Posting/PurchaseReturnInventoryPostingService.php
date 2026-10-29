<?php

namespace App\Modules\PurchaseReturn\Services\Posting;

use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;
use App\Modules\Inventory\Enums\ProductSerialStatusEnum;
use App\Modules\Inventory\Models\ProductBatch;
use App\Modules\Inventory\Models\ProductSerial;
use App\Modules\Inventory\Services\InventoryBatchService;
use App\Modules\Inventory\Services\InventoryPostingService;
use App\Modules\Inventory\Services\InventorySerialService;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;

class PurchaseReturnInventoryPostingService
{
    public function __construct(
        protected InventoryPostingService $inventoryPostingService,
        protected InventoryBatchService $inventoryBatchService,
        protected InventorySerialService $serialService,
    ) {}

    public function post(PurchaseReturn $purchaseReturn): void
    {
        $purchaseReturn->loadMissing(['items.product', 'items.batches', 'items.serials']);

        foreach ($purchaseReturn->items as $item) {
            if ($item->product && ! $item->product->requiresStock()) {
                continue;
            }

            $warehouseId = $item->warehouse_id;

            // Batch-tracked items
            if ($item->product->isBatchTracked() && $item->batches->isNotEmpty()) {
                foreach ($item->batches as $batchItem) {
                    $batch = $batchItem->product_batch_id
                        ? ProductBatch::query()->find($batchItem->product_batch_id)
                        : $this->inventoryBatchService->findByBatchNumber(
                            $batchItem->batch_no,
                            $item->product_id,
                            $warehouseId,
                            $item->product_variant_id,
                        );

                    if ($batch && ! $batchItem->product_batch_id) {
                        $batchItem->update(['product_batch_id' => $batch->id]);
                    }

                    $movement = new InventoryMovementData(
                        productId: $item->product_id,
                        productVariantId: $item->product_variant_id,
                        warehouseId: $warehouseId,
                        quantity: (float) $batchItem->quantity,
                        unitCost: (float) $item->unit_cost,
                        transactionType: InventoryTransactionTypeEnum::PURCHASE_RETURN->value,
                        transactionDate: $purchaseReturn->return_date,
                        referenceType: PurchaseReturn::class,
                        referenceId: $purchaseReturn->id,
                        referenceNo: $purchaseReturn->return_no,
                        batchId: $batch?->id,
                        serialId: null,
                        remarks: "Purchase Return {$purchaseReturn->return_no} Batch {$batchItem->batch_no}",
                    );

                    $this->inventoryPostingService->stockOut($movement);
                }
                continue;
            }

            // Serial-tracked items
            if ($item->product->isSerialTracked() && $item->serials->isNotEmpty()) {
                foreach ($item->serials as $serialItem) {
                    /** @var ProductSerial|null $serial */
                    $serial = $serialItem->product_serial_id
                        ? ProductSerial::query()->find($serialItem->product_serial_id)
                        : $this->serialService->findBySerialNumber($serialItem->serial_number);

                    if ($serial && ! $serialItem->product_serial_id) {
                        $serialItem->update(['product_serial_id' => $serial->id]);
                    }

                    $movement = new InventoryMovementData(
                        productId: $item->product_id,
                        productVariantId: $item->product_variant_id,
                        warehouseId: $warehouseId,
                        quantity: 1,
                        unitCost: (float) $item->unit_cost,
                        transactionType: InventoryTransactionTypeEnum::PURCHASE_RETURN->value,
                        transactionDate: $purchaseReturn->return_date,
                        referenceType: PurchaseReturn::class,
                        referenceId: $purchaseReturn->id,
                        referenceNo: $purchaseReturn->return_no,
                        batchId: null,
                        serialId: $serial?->id,
                        remarks: "Purchase Return {$purchaseReturn->return_no} Serial {$serialItem->serial_number}",
                    );

                    $this->inventoryPostingService->stockOut($movement);

                    if ($serial) {
                        $serial->update([
                            'status' => ProductSerialStatusEnum::RETURNED,
                            'sold_at' => null,
                        ]);
                    }
                }
                continue;
            }

            // Standard non-tracked items
            $movement = new InventoryMovementData(
                productId: $item->product_id,
                productVariantId: $item->product_variant_id,
                warehouseId: $warehouseId,
                quantity: (float) ($item->base_quantity ?? $item->returned_quantity),
                unitCost: (float) $item->unit_cost,
                transactionType: InventoryTransactionTypeEnum::PURCHASE_RETURN->value,
                transactionDate: $purchaseReturn->return_date,
                referenceType: PurchaseReturn::class,
                referenceId: $purchaseReturn->id,
                referenceNo: $purchaseReturn->return_no,
                remarks: "Purchase Return {$purchaseReturn->return_no}",
            );

            $this->inventoryPostingService->stockOut($movement);
        }
    }
}
