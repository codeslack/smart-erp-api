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
use App\Modules\Sales\Models\SaleItemBatch;
use App\Modules\Sales\Models\SaleItemSerial;
use App\Modules\Inventory\Services\InventoryBatchService;
use App\Modules\Inventory\Services\InventoryPostingService;
use App\Modules\Inventory\Services\InventorySerialService;
use App\Modules\Inventory\Services\Reversal\InventoryStockInReversalService;
use App\Modules\Product\Models\Product;
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
            $product = $item->product;

            if (! $product && $item->product_id !== null) {
                $product = Product::withTrashed()->find($item->product_id);
            }

            if (! $product) {
                throw new BusinessException(
                    'The product associated with this Sales Return item no longer exists.'
                );
            }

            if (! $product->requiresStock()) {
                continue;
            }

            if ($product->trashed()) {
                throw new BusinessException(
                    "Inventory product [{$product->name}] must be restored before confirming this Sales Return."
                );
            }

            $warehouseId = $item->warehouse_id;

            // Batch-tracked products must return against batches from the original sale.
            if ($product->isBatchTracked()) {
                if ($item->batches->isEmpty()) {
                    throw new BusinessException(
                        "Product [{$product->name}] requires batch details for return."
                    );
                }

                $returnedQuantity = (float) $item->batches->sum('quantity');
                if (abs($returnedQuantity - (float) $item->returned_quantity) > 0.0001) {
                    throw new BusinessException(
                        'Sales return batch quantities do not match the returned item quantity.'
                    );
                }

                $postedBaseQuantity = 0.0;

                foreach ($item->batches as $batchItem) {
                    if (! $batchItem->product_batch_id) {
                        throw new BusinessException(
                            "Batch [{$batchItem->batch_no}] is not linked to the original sale allocation."
                        );
                    }

                    $sourceAllocation = SaleItemBatch::query()
                        ->where('sale_item_id', $item->sale_item_id)
                        ->where('product_batch_id', $batchItem->product_batch_id)
                        ->where('batch_no', $batchItem->batch_no)
                        ->lockForUpdate()
                        ->first();

                    if (! $sourceAllocation || (float) $sourceAllocation->quantity <= 0) {
                        throw new BusinessException(
                            "Batch [{$batchItem->batch_no}] was not allocated to the original sale item."
                        );
                    }

                    $batchBaseQuantity = round(
                        (float) $batchItem->quantity
                            * ((float) $sourceAllocation->base_quantity / (float) $sourceAllocation->quantity),
                        4
                    );

                    $batch = $this->inventoryBatchService->lock((int) $batchItem->product_batch_id);
                    $this->inventoryBatchService->validateOwnership(
                        $batch,
                        (int) $item->product_id,
                        (int) $warehouseId,
                        $item->product_variant_id !== null ? (int) $item->product_variant_id : null,
                    );

                    $postedBaseQuantity += $batchBaseQuantity;

                    $movement = new InventoryMovementData(
                        productId: $item->product_id,
                        productVariantId: $item->product_variant_id,
                        warehouseId: $warehouseId,
                        quantity: $batchBaseQuantity,
                        unitCost: (float) $sourceAllocation->unit_cost,
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

                if (abs($postedBaseQuantity - (float) $item->base_quantity) > 0.0001) {
                    throw new BusinessException(
                        'Sales return batch base quantities do not match the item base quantity.'
                    );
                }

                continue;
            }

            // Serial tracked: every returned base unit must map to a sold serial.
            if ($product->isSerialTracked()) {
                if ($item->serials->isEmpty()) {
                    throw new BusinessException(
                        "Product [{$product->name}] requires serial numbers for return."
                    );
                }

                $baseQuantity = (float) ($item->base_quantity ?? $item->returned_quantity);
                if (abs($item->serials->count() - round($baseQuantity)) > 0.0001) {
                    throw new BusinessException(
                        'Sales return serial count does not match the returned base quantity.'
                    );
                }

                foreach ($item->serials as $serialItem) {
                    $sourceAllocation = SaleItemSerial::query()
                        ->where('sale_item_id', $item->sale_item_id)
                        ->where('serial_number', $serialItem->serial_number)
                        ->lockForUpdate()
                        ->first();

                    if (! $sourceAllocation) {
                        throw new BusinessException(
                            "Serial [{$serialItem->serial_number}] was not allocated to the original sale item."
                        );
                    }

                    if (
                        $serialItem->product_serial_id !== null
                        && $sourceAllocation->product_serial_id !== null
                        && (int) $serialItem->product_serial_id !== (int) $sourceAllocation->product_serial_id
                    ) {
                        throw new BusinessException(
                            "Serial [{$serialItem->serial_number}] does not match the original sale allocation."
                        );
                    }

                    /** @var ProductSerial|null $serial */
                    $serial = $sourceAllocation->product_serial_id
                        ? ProductSerial::query()->find($sourceAllocation->product_serial_id)
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
                        unitCost: (float) $sourceAllocation->unit_cost,
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

            // A cancelled return puts the serial back into its original sold state.
            if ($ledger->product_serial_id !== null) {
                $serial = $this->serialService->lockForStockOut(
                    serialId: (int) $ledger->product_serial_id,
                    productId: (int) $ledger->product_id,
                    warehouseId: (int) $ledger->warehouse_id,
                    productVariantId: $ledger->product_variant_id !== null
                        ? (int) $ledger->product_variant_id
                        : null,
                    productBatchId: $ledger->product_batch_id !== null
                        ? (int) $ledger->product_batch_id
                        : null,
                );

                $this->serialService->markSold($serial);
            }

            $this->stockInReversalService->reverse($ledger, $reversalData);
        }
    }
}
