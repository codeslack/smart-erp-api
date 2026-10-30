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
use App\Modules\Sales\Models\Sale;

class SaleInventoryPostingService
{
    public function __construct(
        protected InventoryPostingService $inventoryPostingService,
        protected InventoryBatchService $inventoryBatchService,
        protected InventorySerialService $serialService,
        protected InventoryStockOutReversalService $stockOutReversalService
    ) {}

    public function post(Sale $sale): void
    {
        $sale->loadMissing(['items.product', 'items.batches', 'items.serials']);

        foreach ($sale->items as $item) {
            if ($item->product && ! $item->product->requiresStock()) {
                continue;
            }

            $warehouseId = $item->warehouse_id;

            // Batch tracked
            if ($item->product->isBatchTracked() && $item->batches->isNotEmpty()) {
                foreach ($item->batches as $batchItem) {
                    $batch = $batchItem->product_batch_id
                        ? ProductBatch::query()->find($batchItem->product_batch_id)
                        : $this->inventoryBatchService->findByBatchNumber(
                            $batchItem->batch_no,
                            $item->product_id,
                            $warehouseId,
                            $item->product_variant_id
                        );

                    if ($batch && ! $batchItem->product_batch_id) {
                        $batchItem->update(['product_batch_id' => $batch->id]);
                    }

                    $movement = new InventoryMovementData(
                        productId: $item->product_id,
                        productVariantId: $item->product_variant_id,
                        warehouseId: $warehouseId,
                        quantity: (float) $batchItem->quantity,
                        unitCost: (float) $item->unit_price,
                        transactionType: InventoryTransactionTypeEnum::SALE->value,
                        transactionDate: $sale->sale_date,
                        referenceType: Sale::class,
                        referenceId: $sale->id,
                        referenceNo: $sale->sale_no,
                        batchId: $batch?->id,
                        serialId: null,
                        remarks: "Sale {$sale->sale_no} Batch {$batchItem->batch_no}",
                    );

                    $this->inventoryPostingService->stockOut($movement);
                }
                continue;
            }

            // Serial tracked
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
                        unitCost: (float) $item->unit_price,
                        transactionType: InventoryTransactionTypeEnum::SALE->value,
                        transactionDate: $sale->sale_date,
                        referenceType: Sale::class,
                        referenceId: $sale->id,
                        referenceNo: $sale->sale_no,
                        batchId: null,
                        serialId: $serial?->id,
                        remarks: "Sale {$sale->sale_no} Serial {$serialItem->serial_number}",
                    );

                    $this->inventoryPostingService->stockOut($movement);

                    if ($serial) {
                        $serial->update([
                            'status' => ProductSerialStatusEnum::SOLD,
                            'sold_at' => now(),
                        ]);
                    }
                }
                continue;
            }

            // Standard products
            $movement = new InventoryMovementData(
                productId: $item->product_id,
                productVariantId: $item->product_variant_id,
                warehouseId: $warehouseId,
                quantity: (float) ($item->base_quantity ?? $item->quantity),
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

    public function reverse(Sale $sale): void
    {
        $ledgers = StockLedger::query()
            ->where('tenant_id', tenantId())
            ->where('referenceable_type', Sale::class)
            ->where('referenceable_id', $sale->id)
            ->get();

        foreach ($ledgers as $ledger) {
            $reversalData = new InventoryReversalData(
                reason: "Cancellation of Sale {$sale->sale_no}",
                reversalDate: now(),
                metadata: ['sale_id' => $sale->id]
            );

            $this->stockOutReversalService->reverse($ledger, $reversalData);
        }
    }
}
