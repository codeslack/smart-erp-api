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
        protected InventoryStockInReversa    public function post(SalesReturn $salesReturn): void
    {
        $salesReturn->loadMissing(['items.product', 'items.batches', 'items.serials']);

        foreach ($salesReturn->items as $item) {
            if (! $item->product || ! $item->product->requiresStock()) {
                continue;
            }

            $warehouseId = $item->warehouse_id;
            $baseQuantity = (float) (
                $item->base_quantity
                ?? $item->returned_quantity
            );

            if ($item->product->isBatchTracked()) {
                if ($item->batches->isEmpty()) {
                    throw new BusinessException(
                        "Product [{$item->product->name}] requires returned batch details."
                    );
                }

                $batchQuantitySum = (float) $item->batches->sum('quantity');
                $batchBaseQuantitySum = 0.0;

                if (abs($batchQuantitySum - (float) $item->returned_quantity) > 0.0001) {
                    throw new BusinessException(
                        'Sales return batch quantities do not match the return line quantity.'
                    );
                }

                foreach ($item->batches as $batchItem) {
                    $batchBaseQuantity = (float) (
                        $batchItem->base_quantity
                        ?? $batchItem->quantity
                    );

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
                            "Original batch [{$batchItem->batch_no}] was not found."
                        );
                    }

                    if (! $batchItem->product_batch_id) {
                        $batchItem->update(['product_batch_id' => $batch->id]);
                    }

                    $batchBaseQuantitySum += $batchBaseQuantity;

                    $movement = new InventoryMovementData(
                        productId: $item->product_id,
                        productVariantId: $item->product_variant_id,
                        warehouseId: $warehouseId,
                        quantity: $batchBaseQuantity,
                        unitCost: (float) $batch->unit_cost,
                        transactionType: InventoryTransactionTypeEnum::SALE_RETURN->value,
                        transactionDate: $salesReturn->return_date,
                        referenceType: SalesReturn::class,
                        referenceId: $salesReturn->id,
                        referenceNo: $salesReturn->return_no,
                        batchId: $batch->id,
                        serialId: null,
                        remarks: "Sales Return {$salesReturn->return_no} Batch {$batchItem->batch_no}",
                    );

                    $this->inventoryPostingService->stockIn($movement);
                }

                if (abs($batchBaseQuantitySum - $baseQuantity) > 0.0001) {
                    throw new BusinessException(
                        'Sales return batch base quantities do not match the return line base quantity.'
                    );
                }

                continue;
            }

            if ($item->product->isSerialTracked()) {
                if ($item->serials->isEmpty()) {
                    throw new BusinessException(
                        "Product [{$item->product->name}] requires returned serial numbers."
                    );
                }

                if (
                    abs($baseQuantity - round($baseQuantity)) > 0.0001
                    || $item->serials->count() !== (int) round($baseQuantity)
                ) {
                    throw new BusinessException(
                        'Sales return serial count must equal the return line base quantity.'
                    );
                }

                foreach ($item->serials as $serialItem) {
                    /** @var ProductSerial|null $serial */
                    $serial = $serialItem->product_serial_id
                        ? ProductSerial::query()->find($serialItem->product_serial_id)
                        : $this->serialService->findBySerialNumber($serialItem->serial_number);

                    if (! $serial) {
                        throw new BusinessException(
                            "Original serial [{$serialItem->serial_number}] was not found."
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
                        unitCost: (float) ($serial->purchase_cost ?? $item->unit_cost),
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

            $movement = new InventoryMovementData(
                productId: $item->product_id,
                productVariantId: $item->product_variant_id,
                warehouseId: $warehouseId,
                quantity: $baseQuantity,
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

vice->stockIn($movement);
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
