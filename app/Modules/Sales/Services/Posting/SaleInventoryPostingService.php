<?php

namespace App\Modules\Sales\Services\Posting;

use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Data\InventoryReversalData;
use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;
use App\Modules\Inventory\Enums\ProductSerialStatusEnum;
use App\Modules\Inventory\Models\ProductBatch;
use App\Modules\Inventory\Models\ProductSerial;
use App\Modules\Inventory\Models\StockLedger;
use App\Modules\Inventory\Services\InventoryBatchService;
use App\Modules\Inventory\Services\InventoryPostingService;
use App\Modules\Inventory\Services\InventorySerialService;
use App\Modules\Inventory\Services\Reversal\InventoryStockOutReversalService;
use App\Core\Exceptions\BusinessException;
use App\Modules\ProductUnit\Models\ProductUnit;
use App\Modules\ProductUnit\Services\ProductUnitService;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;

class SaleInventoryPostingService
{
    public function __construct(
        protected InventoryPostingService $inventoryPostingService,
        protected InventoryBatchService $inventoryBatchService,
        protected InventorySerialService $serialService,
        protected InventoryStockOutReversalService $stockOutReversalService,
        protected ProductUnitService $productUnitService
    ) {}

    public function post(Sale $sale): void
    {
        $sale->loadMissing(['items.product', 'items.batches', 'items.serials']);

        foreach ($sale->items as $item) {
            if (! $item->product || ! $item->product->requiresStock()) {
                continue;
            }

            $productUnit = $this->resolveProductUnit($item);

            $baseQuantity = $item->base_quantity !== null
                ? (float) $item->base_quantity
                : ($productUnit
                    ? $this->productUnitService->toBaseQuantity(
                        $productUnit,
                        (float) $item->quantity
                    )
                    : (float) $item->quantity);

            $warehouseId = $item->warehouse_id;

            if ($item->product->isBatchTracked()) {
                if ($item->batches->isEmpty()) {
                    throw new BusinessException(
                        "Product [{$item->product->name}] requires batch details."
                    );
                }

                $batchQuantitySum = (float) $item->batches->sum('quantity');

                if (abs($batchQuantitySum - (float) $item->quantity) > 0.0001) {
                    throw new BusinessException(
                        'Sale batch quantities do not match the document quantity.'
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

                    $batch = $batchItem->product_batch_id
                        ? ProductBatch::query()->find($batchItem->product_batch_id)
                        : $this->inventoryBatchService->findByBatchNumber(
                            $batchItem->batch_no,
                            $item->product_id,
                            $warehouseId,
                            $item->product_variant_id
                        );

                    if (! $batch) {
                        throw new BusinessException(
                            "Batch [{$batchItem->batch_no}] was not found for this product and warehouse."
                        );
                    }

                    if (! $batchItem->product_batch_id) {
                        $batchItem->update(['product_batch_id' => $batch->id]);
                    }

                    $postedBaseQuantity += $batchBaseQuantity;

                    $movement = new InventoryMovementData(
                        productId: $item->product_id,
                        productVariantId: $item->product_variant_id,
                        warehouseId: $warehouseId,
                        quantity: $batchBaseQuantity,
                        unitCost: (float) $item->unit_price,
                        transactionType: InventoryTransactionTypeEnum::SALE->value,
                        transactionDate: $sale->sale_date,
                        referenceType: Sale::class,
                        referenceId: $sale->id,
                        referenceNo: $sale->sale_no,
                        batchId: $batch->id,
                        serialId: null,
                        remarks: "Sale {$sale->sale_no} Batch {$batchItem->batch_no}",
                    );

                    $this->inventoryPostingService->stockOut($movement);
                }

                if (abs($postedBaseQuantity - $baseQuantity) > 0.0001) {
                    throw new BusinessException(
                        'Sale batch base quantities do not match the item base quantity.'
                    );
                }

                continue;
            }

            if ($item->product->isSerialTracked()) {
                if ($item->serials->isEmpty()) {
                    throw new BusinessException(
                        "Product [{$item->product->name}] requires serial numbers."
                    );
                }

                if (abs($baseQuantity - round($baseQuantity)) > 0.0001) {
                    throw new BusinessException(
                        'Serial-tracked sale quantity must resolve to a whole number of base units.'
                    );
                }

                if ($item->serials->count() !== (int) round($baseQuantity)) {
                    throw new BusinessException(
                        'Sale serial count does not match the item base quantity.'
                    );
                }

                foreach ($item->serials as $serialItem) {
                    $serial = $serialItem->product_serial_id
                        ? ProductSerial::query()->find($serialItem->product_serial_id)
                        : $this->serialService->findBySerialNumber($serialItem->serial_number);

                    if (! $serial) {
                        throw new BusinessException(
                            "Serial [{$serialItem->serial_number}] was not found for this product."
                        );
                    }

                    if (! $serialItem->product_serial_id) {
                        $serialItem->update(['product_serial_id' => $serial->id]);
                    }

                    $movement = new InventoryMovementData(
                        productId: $item->product_id,
                        productVariantId: $item->product_variant_id,
                        warehouseId: $warehouseId,
                        quantity: 1,
                        unitCost: (float) $item->unit_price,
                        transactionType: InventoryTransactionTypeEnum::SALE->value,
                        transactionDate: $sale->sale_date,
                        referenceType: Sale::class,
                        referenceId: $sale->id,
                        referenceNo: $sale->sale_no,
                        batchId: null,
                        serialId: $serial->id,
                        remarks: "Sale {$sale->sale_no} Serial {$serialItem->serial_number}",
                    );

                    $this->inventoryPostingService->stockOut($movement);

                    $serial->update([
                        'status' => ProductSerialStatusEnum::SOLD,
                        'sold_at' => now(),
                    ]);
                }

                continue;
            }

            $movement = new InventoryMovementData(
                productId: $item->product_id,
                productVariantId: $item->product_variant_id,
                warehouseId: $warehouseId,
                quantity: $baseQuantity,
                unitCost: (float) $item->unit_price,
                transactionType: InventoryTransactionTypeEnum::SALE->value,
                transactionDate: $sale->sale_date,
                referenceType: Sale::class,
                referenceId: $sale->id,
                referenceNo: $sale->sale_no,
                remarks: "Sale {$sale->sale_no}",
            );

            $this->inventoryPostingService->stockOut($movement);
        }
    }

    protected function resolveProductUnit(
        SaleItem $item
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
                'The sales unit is not configured for this product.'
            );
        }

        $this->productUnitService
            ->ensureSalesUnit($productUnit);

        return $productUnit;
    }

    public function reverse(Sale $sale): void
    {
        $ledgers = StockLedger::query()
            ->where('tenant_id', tenantId())
            ->where('referenceable_type', Sale::class)
            ->where('referenceable_id', $sale->id)
            ->get();

        foreach ($ledgers as $ledger) {
            $reversalData = new InventoryReversalData(
                stockLedgerId: (int) $ledger->id,
                reversalDate: now(),
                referenceType: Sale::class,
                referenceId: (int) $sale->id,
                referenceNo: $sale->sale_no,
                remarks: "Cancellation of Sale {$sale->sale_no}"
            );

            $this->stockOutReversalService->reverse($ledger, $reversalData);
        }
    }
}
