<?php

namespace App\Modules\Purchase\Services\Posting;

use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseItem;
use App\Modules\Purchase\Models\PurchaseItemBatch;
use App\Modules\Purchase\Models\PurchaseItemSerial;
use App\Core\Exceptions\BusinessException;
use App\Modules\ProductUnit\Models\ProductUnit;
use App\Modules\ProductUnit\Services\ProductUnitService;
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
        protected ProductUnitService $productUnitService,
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

            $productUnit = $this->resolveProductUnit($item);

            $baseUnitCost = $item->base_unit_cost !== null
                ? (float) $item->base_unit_cost
                : ($productUnit
                    ? $this->productUnitService->toBaseUnitCost(
                        $productUnit,
                        (float) $item->unit_cost
                    )
                    : (float) $item->unit_cost);

            $exchangeRate = (string) ($purchase->exchange_rate ?? '1.000000');

            if (! is_numeric($exchangeRate) || bccomp($exchangeRate, '0', 8) <= 0) {
                throw new BusinessException('Purchase exchange rate must be greater than zero.');
            }

            // base_unit_cost is normalized to the product's base UOM but is
            // still expressed in the purchase currency until this conversion.
            $baseUnitCost = (float) bcmul(
                (string) $baseUnitCost,
                $exchangeRate,
                8
            );

            $baseQuantity = $item->base_quantity !== null
                ? (float) $item->base_quantity
                : ($productUnit
                    ? $this->productUnitService->toBaseQuantity(
                        $productUnit,
                        (float) $item->quantity
                    )
                    : (float) $item->quantity);

            $warehouseId = $item->warehouse_id ?? $purchase->warehouse_id;

            // Batch-tracked items
            if ($item->product->isBatchTracked()) {
                if ($item->batches->isEmpty()) {
                    throw new BusinessException(
                        "Product [{$item->product->name}] requires batch details."
                    );
                }

                $batchQuantitySum = (float) $item->batches->sum('quantity');

                if (abs($batchQuantitySum - (float) $item->quantity) > 0.0001) {
                    throw new BusinessException(
                        'Purchase batch quantities do not match the document quantity.'
                    );
                }

                $postedBaseQuantity = 0.0;

                foreach ($item->batches as $batchItem) {
                    $batchBaseQuantity = $batchItem->base_quantity !== null
                        ? (float) $batchItem->base_quantity
                        : ($productUnit
                            ? $this->productUnitService->toBaseQuantity(
                                $productUnit,
                                (float) $batchItem->quantity
                            )
                            : (float) $batchItem->quantity);

                    $postedBaseQuantity += $batchBaseQuantity;

                    $batch = $this->resolveBatch(
                        $purchase,
                        $item,
                        $batchItem,
                        $warehouseId,
                        $baseUnitCost,
                        $batchBaseQuantity
                    );

                    $movement = new InventoryMovementData(
                        productId: $item->product_id,
                        productVariantId: $item->product_variant_id,
                        warehouseId: $warehouseId,
                        quantity: $batchBaseQuantity,
                        unitCost: $baseUnitCost,
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

                if (abs($postedBaseQuantity - $baseQuantity) > 0.0001) {
                    throw new BusinessException(
                        'Purchase batch base quantities do not match the item base quantity.'
                    );
                }

                continue;
            }

            // Serial-tracked items
            if ($item->product->isSerialTracked()) {
                if ($item->serials->isEmpty()) {
                    throw new BusinessException(
                        "Product [{$item->product->name}] requires serial numbers."
                    );
                }

                if (abs($baseQuantity - round($baseQuantity)) > 0.0001) {
                    throw new BusinessException(
                        'Serial-tracked purchase quantity must resolve to a whole number of base units.'
                    );
                }

                if ($item->serials->count() !== (int) round($baseQuantity)) {
                    throw new BusinessException(
                        'Purchase serial count does not match the item base quantity.'
                    );
                }

                foreach ($item->serials as $serialItem) {
                    $serial = $this->serialRegistrationService->register(
                        productId: $item->product_id,
                        warehouseId: $warehouseId,
                        serialNumber: $serialItem->serial_number,
                        purchaseCost: $baseUnitCost,
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
                        unitCost: $baseUnitCost,
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
                quantity: $baseQuantity,
                unitCost: $baseUnitCost,
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

    protected function resolveProductUnit(
        PurchaseItem $item
    ): ?ProductUnit {
        $product = $item->product;

        if (! $product || ! $product->requiresStock()) {
            return null;
        }

        $unitId = (int) (
            $item->unit_id
            ?? $product->unit_id
            ?? 0
        );

        if ($unitId <= 0) {
            return null;
        }

        $productUnit = $this->productUnitService
            ->findByProductAndUnit(
                (int) $item->product_id,
                $unitId
            );

        if (
            ! $productUnit
            && (int) ($product->unit_id ?? 0) === $unitId
        ) {
            $productUnit = $this->productUnitService
                ->syncBaseUnit($product);
        }

        if (! $productUnit) {
            throw new BusinessException(
                'The purchase unit is not configured for this product.'
            );
        }

        $this->productUnitService
            ->ensurePurchaseUnit($productUnit);

        return $productUnit;
    }

    protected function resolveBatch(
        Purchase $purchase,
        PurchaseItem $item,
        PurchaseItemBatch $batchItem,
        int $warehouseId,
        float $baseUnitCost,
        float $batchBaseQuantity
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
            'unit_cost' => $baseUnitCost,
            'sourceable_type' => Purchase::class,
            'sourceable_id' => $purchase->id,
            'remarks' => "Purchase {$purchase->purchase_no}",
        ]);

        $this->inventoryBatchService->initializeOriginalQuantity(
            $draft,
            $batchBaseQuantity,
        );

        return $this->inventoryBatchService->activate($draft);
    }
}
