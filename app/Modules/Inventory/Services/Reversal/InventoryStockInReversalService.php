<?php

namespace App\Modules\Inventory\Services\Reversal;

use App\Core\Exceptions\BusinessException;

use App\Modules\Inventory\Models\StockLedger;
use App\Modules\Inventory\Data\InventoryReversalData;

use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;
use App\Modules\Inventory\Enums\InventoryCostLayerStatusEnum;

use App\Modules\Inventory\Services\InventorySettingsService;

use App\Modules\Inventory\Repositories\Contracts\StockLedgerRepositoryInterface;
use App\Modules\Inventory\Repositories\Contracts\ProductStockRepositoryInterface;
use App\Modules\Inventory\Repositories\Contracts\ProductBatchRepositoryInterface;
use App\Modules\Inventory\Repositories\Contracts\InventoryCostLayerRepositoryInterface;

class InventoryStockInReversalService
{
    public function __construct(
        protected InventorySettingsService $settings,
        protected StockLedgerRepositoryInterface $stockLedgerRepository,
        protected ProductStockRepositoryInterface $productStockRepository,
        protected InventoryCostLayerRepositoryInterface $layerRepository,
        protected ProductBatchRepositoryInterface $batchRepository,
    ) {}

    public function reverse(
        StockLedger $ledger,
        InventoryReversalData $data
    ) {
        $layer = $this->layerRepository
            ->findByStockLedgerId($ledger->id);

        if ($this->settings->isFifo() && ! $layer) {
            throw new BusinessException(
                'FIFO cost layer not found for stock-in ledger.',
                'COST_LAYER_NOT_FOUND'
            );
        }

        if (
            $this->settings->isFifo()
            && $layer
            && bccomp(
                (string) $layer->remaining_quantity,
                (string) $layer->original_quantity,
                4
            ) !== 0
        ) {
            throw new BusinessException(
                'Stock-in cannot be reversed because its FIFO cost layer has already been consumed.',
                'STOCK_IN_ALREADY_CONSUMED'
            );
        }

        $stock = $this->productStockRepository
            ->findForUpdate(
                productId: $ledger->product_id,
                productVariantId: $ledger->product_variant_id,
                warehouseId: $ledger->warehouse_id
            );

        if (! $stock) {
            throw new BusinessException(
                'Product stock record not found.',
                'STOCK_NOT_FOUND'
            );
        }

        $reverseQuantity = (float) $ledger->quantity_in;

        if (
            $reverseQuantity <= 0
            || bccomp(
                (string) $stock->quantity,
                (string) $reverseQuantity,
                4
            ) < 0
        ) {
            throw new BusinessException(
                'Stock-in cannot be reversed because current stock is insufficient.',
                'INSUFFICIENT_STOCK_FOR_REVERSAL'
            );
        }

        $currentQuantity = (float) $stock->quantity;
        $currentAverageCost = (float) $stock->average_cost;

        $currentValue = $currentQuantity * $currentAverageCost;
        $reverseValue = (float) $ledger->total_cost;

        $newQuantity = $currentQuantity - $reverseQuantity;
        $newAverageCost = 0.0;

        if ($newQuantity > 0) {
            $newValue = max(0, $currentValue - $reverseValue);
            $newAverageCost = $newValue / $newQuantity;
        }

        $this->productStockRepository->updateBalance(
            stock: $stock,
            quantity: $newQuantity,
            averageCost: $newAverageCost,
            movementDate: $data->reversalDate
        );

        if ($ledger->product_batch_id) {
            $batch = $this->batchRepository->findForUpdate(
                $ledger->product_batch_id
            );

            if (! $batch) {
                throw new BusinessException(
                    'Product batch not found.',
                    'BATCH_NOT_FOUND'
                );
            }

            $currentRemaining = (float) $batch->remaining_quantity;

            if (
                bccomp(
                    (string) $currentRemaining,
                    (string) $reverseQuantity,
                    4
                ) < 0
            ) {
                throw new BusinessException(
                    'Batch quantity is insufficient for reversal.',
                    'INSUFFICIENT_BATCH_QUANTITY_FOR_REVERSAL'
                );
            }

            $this->batchRepository->update(
                $batch,
                [
                    'remaining_quantity' =>
                        $currentRemaining - $reverseQuantity,
                ]
            );
        }

        if ($this->settings->isFifo() && $layer) {
            $this->layerRepository->update(
                $layer,
                [
                    'remaining_quantity' => 0,
                    'status' => InventoryCostLayerStatusEnum::REVERSED,
                ]
            );
        }

        $sequence = $this->stockLedgerRepository
            ->nextSequenceNumber(
                productId: $ledger->product_id,
                productVariantId: $ledger->product_variant_id,
                warehouseId: $ledger->warehouse_id
            );

        return $this->stockLedgerRepository->create([
            'product_id' => $ledger->product_id,
            'product_variant_id' => $ledger->product_variant_id,
            'warehouse_id' => $ledger->warehouse_id,
            'product_batch_id' => $ledger->product_batch_id,
            'product_serial_id' => $ledger->product_serial_id,
            'transaction_type' => InventoryTransactionTypeEnum::REVERSAL_OUT,
            'transaction_date' => $data->reversalDate,
            'sequence_no' => $sequence,
            'referenceable_type' => $data->referenceType,
            'referenceable_id' => $data->referenceId,
            'reference_no' => $data->referenceNo,
            'reversal_of_ledger_id' => $ledger->id,
            'quantity_in' => 0,
            'quantity_out' => $reverseQuantity,
            'unit_cost' => (float) $ledger->unit_cost,
            'total_cost' => $reverseValue,
            'balance_quantity' => $newQuantity,
            'balance_average_cost' => $newAverageCost,
            'remarks' => $data->remarks,
            'created_by' => userId(),
        ]);
    }
}