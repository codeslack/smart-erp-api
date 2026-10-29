<?php

namespace App\Modules\Purchase\Services\Posting;

use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseItem;
use App\Modules\Purchase\Models\PurchaseItemBatch;
use App\Modules\Purchase\Models\PurchaseItemSerial;
use App\Modules\Inventory\Services\InventoryPostingService;
use App\Modules\Inventory\Services\InventoryBatchService;
use App\Modules\Inventory\Services\Serial\InventorySerialRegistrationService;
use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;

class PurchaseInventoryPostingService
{
    public function __construct(
        protected InventoryPostingService $inventoryPostingService,
        protected InventoryBatchService $inventoryBatchService,
        protected InventorySerialRegistrationService $serialRegistrationService,
    ) {}

    public function post(Purchase $purchase): void
    {
        // Rule: If linked to a confirmed GRN, stock was already posted by GRN. Do not post again!
        if ($purchase->goods_receipt_note_id !== null) {
            return;
        }

        $purchase->loadMissing(['items.product', 'items.batches', 'items.serials']);

        foreach ($purchase->items as $item) {
            // Rule: Do not post stock for individual lines linked to a GRN item
            if ($item->goods_receipt_note_item_id !== null) {
                continue;
            }

            // Rule: Service / non-stock items must not create inventory movements
            if ($item->product && ! $item->product->requiresStock()) {
                continue;
            }

            $warehouseId = $item->warehouse_id ?? $purchase->warehouse_id;

            // Batch-tracked items
            if ($item->product->isBatchTracked() && $item->batches->isNotEmpty()) {
                foreach ($item->batches as $batchItem) {
                    $batch = $this->resolveBatch($purchase, $item, $batchItem, $warehouseId);

                    $movement = new InventoryMovementData(
                        productId: $item->product_id,
                        productVariantId: $item->product_variant_id,
                        warehouseId: $warehouseId,
                        quantity: (float) $batchItem->quantity,
                        unitCost: (float) $item->unit_cost,
                        transactionType: InventoryTransactionTypeEnum::PURCHASE->value,
                        transactionDate: $purchase->purchase_date,
                        referenceType: Purchase::class,
                        referenceId: $purchase->id,
                        referenceNo: $purchase->purchase_no,
                        batchId: $batch->id,
                        serialId: null,
                        remarks: "Purchase {$purchase->purchase_no} Batch {$batchItem->batch_no}",
                    );

                    $this->inventoryPostingService->stockIn($movement);
                }
                continue;
            }

            // Serial-tracked items
            if ($item->product->isSerialTracked() && $item->serials->isNotEmpty()) {
                foreach ($item->serials as $serialItem) {
                    $serial = $this->serialRegistrationService->register(
                        productId: $item->product_id,
                        warehouseId: $warehouseId,
                        serialNumber: $serialItem->serial_number,
                        purchaseCost: (float) $item->unit_cost,
                        productVariantId: $item->product_variant_id,
                        sourceableType: Purchase::class,
                        sourceableId: $purchase->id,
                        remarks: "Purchase {$purchase->purchase_no}",
                    );

                    $movement = new InventoryMovementData(
                        productId: $item->product_id,
                        productVariantId: $item->product_variant_id,
                        warehouseId: $warehouseId,
                        quantity: 1,
                        unitCost: (float) $item->unit_cost,
                        transactionType: InventoryTransactionTypeEnum::PURCHASE->value,
                        transactionDate: $purchase->purchase_date,
                        referenceType: Purchase::class,
                        referenceId: $purchase->id,
                        referenceNo: $purchase->purchase_no,
                        batchId: null,
                        serialId: $serial->id,
                        remarks: "Purchase {$purchase->purchase_no} Serial {$serialItem->serial_number}",
                    );

                    $this->inventoryPostingService->stockIn($movement);
                }
                continue;
            }

            // Standard non-tracked items
            $movement = new InventoryMovementData(
                productId: $item->product_id,
                productVariantId: $item->product_variant_id,
                warehouseId: $warehouseId,
                quantity: (float) ($item->base_quantity ?? $item->quantity),
                unitCost: (float) $item->unit_cost,
                transactionType: InventoryTransactionTypeEnum::PURCHASE->value,
                transactionDate: $purchase->purchase_date,
                referenceType: Purchase::class,
                referenceId: $purchase->id,
                referenceNo: $purchase->purchase_no,
                remarks: "Purchase {$purchase->purchase_no}",
            );

            $this->inventoryPostingService->stockIn($movement);
        }
    }

    protected function resolveBatch(
        Purchase $purchase,
        PurchaseItem $item,
        PurchaseItemBatch $batchItem,
        int $warehouseId
    ) {
        $batch = $this->inventoryBatchService->findByBatchNumber(
            $batchItem->batch_no,
            $item->product_id,
            $warehouseId,
            $item->product_variant_id,
        );

        if ($batch !== null) {
            return $batch;
        }

        $draft = $this->inventoryBatchService->createDraft([
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'warehouse_id' => $warehouseId,
            'batch_no' => $batchItem->batch_no,
            'manufacturing_date' => $batchItem->manufacturing_date,
            'expiry_date' => $batchItem->expiry_date,
            'received_at' => $purchase->purchase_date,
            'unit_cost' => (float) $item->unit_cost,
            'sourceable_type' => Purchase::class,
            'sourceable_id' => $purchase->id,
            'remarks' => "Purchase {$purchase->purchase_no}",
        ]);

        $this->inventoryBatchService->initializeOriginalQuantity(
            $draft,
            (float) $batchItem->quantity,
        );

        return $this->inventoryBatchService->activate($draft);
    }
}
