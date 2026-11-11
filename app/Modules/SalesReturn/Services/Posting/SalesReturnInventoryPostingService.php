<?php

namespace App\Modules\SalesReturn\Services\Posting;

use App\Core\Exceptions\BusinessException;
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
use App\Modules\Inventory\Services\Reversal\InventoryStockInReversalService;
use App\Modules\SalesReturn\Models\SalesReturn;

class SalesReturnInventoryPostingService
{
    public function __construct(
        protected InventoryPostingService $inventoryPostingService,
        protected InventoryBatchService $inventoryBatchService,
        protected InventorySerialService $serialService,
        protected InventoryStockInReversalService $stockInReversalService
    ) {}

    public function post(SalesReturn $salesReturn): void
    {
        $salesReturn->loadMissing(['items.product', 'items.batches', 'items.serials']);

        foreach ($salesReturn->items as $item) {
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
                        unitCost: (float) $item->unit_cost,
                        transactionType: InventoryTransactionTypeEnum::SALE_RETURN->value,
                        transactionDate: $salesReturn->return_date,
                        referenceType: SalesReturn::class,
                        referenceId: $salesReturn->id,
                        referenceNo: $salesReturn->return_no,
                        batchId: $batch?->id,
                        serialId: null,
                        remarks: "Sales Return {$salesReturn->return_no} Batch {$batchItem->batch_no}",
                    );

                    $this->inventoryPostingService->stockIn($movement);
                }
                continue;
            }

            // Serial tracked: every returned base unit must map to a sold serial.
            if ($item->product->isSerialTracked()) {
                if ($item->serials->isEmpty()) {
                    throw new BusinessException(
                        "Product [{$item->product->name}] requires serial numbers for return."
                    );
                }

                $baseQuantity = (float) ($item->base_quantity ?? $item->returned_quantity);
                if (abs($item->serials->count() - round($baseQuantity)) > 0.0001) {
                    throw new BusinessException(
                        'Sales return serial count does not match the returned base quantity.'
                    );
                }

                foreach ($item->serials as $serialItem) {
                    /** @var ProductSerial|null $serial */
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
                        unitCost: (float) $item->unit_cost,
                        transactionType: InventoryTransactionTypeEnum::SALE_RETURN->value,
                        transactionDate: $salesReturn->return_date,
                        referenceType: SalesReturn::class,
                        referenceId: $salesReturn->id,
                        referenceNo: $salesReturn->return_no,
                        batchId: null,
                        serialId: $serial->id,
                        remarks: "Sales Return {$salesReturn->return_no} Serial {$serialItem->serial_number}",
                    );

                    $this->inventoryPostingService->stockIn($movement);

                    $serial->update([
                        'status' => ProductSerialStatusEnum::AVAILABLE,
                        'warehouse_id' => $warehouseId,
                        'sold_at' => null,
                    ]);
                }
                continue;
            }

            // Standard products
            $movement = new InventoryMovementData(
                productId: $item->product_id,
                productVariantId: $item->product_variant_id,
                warehouseId: $warehouseId,
                quantity: (float) ($item->base_quantity ?? $item->returned_quantity),
                unitCost: (float) $item->unit_cost,
                transactionType: InventoryTransactionTypeEnum::SALE_RETURN->value,
                transactionDate: $salesReturn->return_date,
                referenceType: SalesReturn::class,
                referenceId: $salesReturn->id,
                referenceNo: $salesReturn->return_no,
                remarks: "Sales Return {$salesReturn->return_no}",
            );

            $this->inventoryPostingService->stockIn($movement);
        }
    }

    public function reverse(SalesReturn $salesReturn): void
    {
        $ledgers = StockLedger::query()
            ->where('tenant_id', tenantId())
            ->where('referenceable_type', SalesReturn::class)
            ->where('referenceable_id', $salesReturn->id)
            ->get();

        foreach ($ledgers as $ledger) {
            $reversalData = new InventoryReversalData(
                stockLedgerId: $ledger->id,
                reversalDate: now(),
                referenceType: SalesReturn::class,
                referenceId: $salesReturn->id,
                referenceNo: $salesReturn->return_no,
                remarks: "Cancellation of Sales Return {$salesReturn->return_no}"
            );

            $this->stockInReversalService->reverse($ledger, $reversalData);
        }
    }
}
