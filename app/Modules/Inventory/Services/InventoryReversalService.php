<?php

namespace App\Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;

use App\Core\Exceptions\BusinessException;

use App\Modules\Inventory\Models\StockLedger;

use App\Modules\Inventory\Data\InventoryReversalData;

use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;
use App\Modules\Inventory\Enums\InventoryCostLayerStatusEnum;

use App\Modules\Inventory\Repositories\Contracts\StockLedgerRepositoryInterface;
use App\Modules\Inventory\Repositories\Contracts\ProductStockRepositoryInterface;
use App\Modules\Inventory\Repositories\Contracts\ProductBatchRepositoryInterface;
use App\Modules\Inventory\Repositories\Contracts\InventoryCostLayerRepositoryInterface;
use App\Modules\Inventory\Repositories\Contracts\InventoryCostLayerConsumptionRepositoryInterface;

class InventoryReversalService
{
    public function __construct(
        protected InventorySettingsService $settings,

        protected StockLedgerRepositoryInterface $stockLedgerRepository,

        protected ProductStockRepositoryInterface $productStockRepository,

        protected InventoryCostLayerRepositoryInterface $layerRepository,

        protected InventoryCostLayerConsumptionRepositoryInterface $consumptionRepository,

        protected ProductBatchRepositoryInterface $batchRepository,
    ) {}

    public function reverse(
        InventoryReversalData $data
    ) {
        return DB::transaction(
            function () use ($data) {

                $ledger =
                    $this->stockLedgerRepository
                        ->findForUpdate(
                            $data->stockLedgerId
                        );

                if (! $ledger) {

                    throw new BusinessException(
                        'Stock ledger not found.',
                        'LEDGER_NOT_FOUND'
                    );
                }

                if (
                    $ledger->reversal_of_ledger_id
                ) {

                    throw new BusinessException(
                        'Reversal ledger cannot be reversed.',
                        'INVALID_REVERSAL'
                    );
                }

                if (
                    $ledger->reversal()->exists()
                ) {

                    throw new BusinessException(
                        'Ledger already reversed.',
                        'LEDGER_ALREADY_REVERSED'
                    );
                }

                return $ledger->quantity_in > 0
                    ? $this->reverseStockIn(
                        $ledger,
                        $data
                    )
                    : $this->reverseStockOut(
                        $ledger,
                        $data
                    );
            }
        );
    }

    private function reverseStockIn(
        StockLedger $ledger,
        InventoryReversalData $data
    ) {
        $layer =
            $this->layerRepository
                ->findByStockLedgerId(
                    $ledger->id
                );

        /*
        * FIFO stock-in must have its original layer.
        */
        if (
            $this->settings->isFifo()
            && ! $layer
        ) {
            throw new BusinessException(
                'FIFO cost layer not found for stock-in ledger.',
                'COST_LAYER_NOT_FOUND'
            );
        }

        /*
        * A stock-in layer cannot be reversed after
        * any quantity has already been consumed.
        */
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

        $stock =
            $this->productStockRepository
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

        $reverseQuantity =
            (float) $ledger->quantity_in;

        if (
            bccomp(
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

        /*
        * For WAC, remove the original inventory value
        * from the current inventory value.
        */
        $currentQuantity =
            (float) $stock->quantity;

        $currentAverageCost =
            (float) $stock->average_cost;

        $currentValue =
            $currentQuantity * $currentAverageCost;

        $reverseValue =
            (float) $ledger->total_cost;

        $newQuantity =
            $currentQuantity - $reverseQuantity;

        $newAverageCost = 0.0;

        if ($newQuantity > 0) {
            $newValue =
                $currentValue - $reverseValue;

            if ($newValue < 0) {
                $newValue = 0;
            }

            $newAverageCost =
                $newValue / $newQuantity;
        }

        $movementDate =
            $data->reversalDate;

        $this->productStockRepository
            ->updateBalance(
                stock: $stock,
                quantity: $newQuantity,
                averageCost: $newAverageCost,
                movementDate: $movementDate
            );

        /*
        |--------------------------------------------------------------------------
        | Reverse Product Batch
        |--------------------------------------------------------------------------
        |
        | The original stock-in increased batch.remaining_quantity.
        | Reversal must remove exactly that quantity.
        |
        | original_quantity must never be changed.
        |
        */

        if ($ledger->product_batch_id) {

            $batch =
                $this->batchRepository
                    ->findForUpdate(
                        $ledger->product_batch_id
                    );

            if (! $batch) {
                throw new BusinessException(
                    'Product batch not found.',
                    'BATCH_NOT_FOUND'
                );
            }

            $currentRemaining =
                (float) $batch->remaining_quantity;

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

        /*
        * FIFO layer is not consumed here.
        * It is the exact layer being reversed.
        */
        if ($this->settings->isFifo() && $layer) {
            $this->layerRepository->update(
                $layer,
                [
                    'remaining_quantity' => 0,
                    'status' => InventoryCostLayerStatusEnum::REVERSED,
                ]
            );
        }

        $sequence =
            $this->stockLedgerRepository
                ->nextSequenceNumber(
                    productId: $ledger->product_id,
                    productVariantId: $ledger->product_variant_id,
                    warehouseId: $ledger->warehouse_id
                );

        return $this->stockLedgerRepository->create([
            'product_id' =>
                $ledger->product_id,

            'product_variant_id' =>
                $ledger->product_variant_id,

            'warehouse_id' =>
                $ledger->warehouse_id,

            'product_batch_id' =>
                $ledger->product_batch_id,

            'product_serial_id' =>
                $ledger->product_serial_id,

            'transaction_type' =>
                \App\Modules\Inventory\Enums\InventoryTransactionTypeEnum::REVERSAL_OUT,

            'transaction_date' =>
                $movementDate,

            'sequence_no' =>
                $sequence,

            'referenceable_type' =>
                $data->referenceType,

            'referenceable_id' =>
                $data->referenceId,

            'reference_no' =>
                $data->referenceNo,

            'reversal_of_ledger_id' =>
                $ledger->id,

            'quantity_in' =>
                0,

            'quantity_out' =>
                $reverseQuantity,

            'unit_cost' =>
                (float) $ledger->unit_cost,

            'total_cost' =>
                $reverseValue,

            'balance_quantity' =>
                $newQuantity,

            'balance_average_cost' =>
                $newAverageCost,

            'remarks' =>
                $data->remarks,

            'created_by' =>
                userId(),
        ]);
    }

    private function reverseStockOut(
        StockLedger $ledger,
        InventoryReversalData $data
    ) {
        /*
        |--------------------------------------------------------------------------
        | Product Stock
        |--------------------------------------------------------------------------
        */

        $stock =
            $this->productStockRepository
                ->findForUpdate(
                    productId:
                        $ledger->product_id,

                    productVariantId:
                        $ledger->product_variant_id,

                    warehouseId:
                        $ledger->warehouse_id
                );

        if (! $stock) {
            throw new BusinessException(
                'Product stock record not found.',
                'STOCK_NOT_FOUND'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Reversal Quantity
        |--------------------------------------------------------------------------
        */

        $reverseQuantity =
            (float) $ledger->quantity_out;

        if ($reverseQuantity <= 0) {
            throw new BusinessException(
                'Invalid stock-out quantity for reversal.',
                'INVALID_REVERSAL_QUANTITY'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | FIFO Consumption Records
        |--------------------------------------------------------------------------
        |
        | A stock-out reversal must restore the EXACT FIFO layers
        | that were consumed by the original stock-out.
        |
        */

        $consumptions = collect();

        if ($this->settings->isFifo()) {

            $consumptions =
                $this->consumptionRepository
                    ->findByStockLedgerId(
                        $ledger->id
                    );

            if ($consumptions->isEmpty()) {
                throw new BusinessException(
                    'FIFO consumption records not found for stock-out ledger.',
                    'CONSUMPTION_NOT_FOUND'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Validate Consumption Quantity
            |--------------------------------------------------------------------------
            */

            $consumedQuantity = 0.0;

            foreach ($consumptions as $consumption) {

                $quantity =
                    (float) $consumption->quantity;

                if ($quantity <= 0) {
                    throw new BusinessException(
                        'Invalid FIFO consumption quantity.',
                        'INVALID_CONSUMPTION_QUANTITY'
                    );
                }

                $consumedQuantity += $quantity;
            }

            if (
                bccomp(
                    (string) $consumedQuantity,
                    (string) $reverseQuantity,
                    4
                ) !== 0
            ) {
                throw new BusinessException(
                    'FIFO consumption quantity does not match stock-out quantity.',
                    'CONSUMPTION_QUANTITY_MISMATCH'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Restore FIFO Layers
            |--------------------------------------------------------------------------
            */

            foreach ($consumptions as $consumption) {

                $layer =
                    $this->layerRepository
                        ->findById(
                            $consumption->inventory_cost_layer_id
                        );

                if (! $layer) {
                    throw new BusinessException(
                        'FIFO cost layer not found.',
                        'COST_LAYER_NOT_FOUND'
                    );
                }

                $restoredQuantity =
                    (float) $layer->remaining_quantity
                    + (float) $consumption->quantity;

                $this->layerRepository->update(
                    $layer,
                    [
                        'remaining_quantity' =>
                            $restoredQuantity,

                        'status' =>
                            InventoryCostLayerStatusEnum::OPEN,
                    ]
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Restore Product Stock
        |--------------------------------------------------------------------------
        */

        $currentQuantity =
            (float) $stock->quantity;

        $newQuantity =
            $currentQuantity + $reverseQuantity;

        /*
        |--------------------------------------------------------------------------
        | Restore Average Cost
        |--------------------------------------------------------------------------
        |
        | For FIFO, the original stock-out does not change the
        | ProductStock average cost in the current posting engine.
        |
        | Therefore restoring the stock-out quantity keeps the
        | current aggregate average cost.
        |
        | For WAC, the original outgoing cost must be added back
        | to the current inventory value.
        |
        */

        $newAverageCost =
            (float) $stock->average_cost;

        if ($this->settings->isWeightedAverage()) {

            $currentValue =
                $currentQuantity
                * (float) $stock->average_cost;

            $restoredValue =
                (float) $ledger->total_cost;

            $newValue =
                $currentValue + $restoredValue;

            if ($newQuantity > 0) {
                $newAverageCost =
                    $newValue / $newQuantity;
            } else {
                $newAverageCost = 0.0;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Update Product Stock
        |--------------------------------------------------------------------------
        */

        $this->productStockRepository
            ->updateBalance(
                stock:
                    $stock,

                quantity:
                    $newQuantity,

                averageCost:
                    $newAverageCost,

                movementDate:
                    $data->reversalDate
            );

        /*
        |--------------------------------------------------------------------------
        | Create Reversal Ledger
        |--------------------------------------------------------------------------
        */

        $sequence =
            $this->stockLedgerRepository
                ->nextSequenceNumber(
                    productId:
                        $ledger->product_id,

                    productVariantId:
                        $ledger->product_variant_id,

                    warehouseId:
                        $ledger->warehouse_id
                );

        return $this->stockLedgerRepository->create([
            'product_id' =>
                $ledger->product_id,

            'product_variant_id' =>
                $ledger->product_variant_id,

            'warehouse_id' =>
                $ledger->warehouse_id,

            'product_batch_id' =>
                $ledger->product_batch_id,

            'product_serial_id' =>
                $ledger->product_serial_id,

            'transaction_type' =>
                InventoryTransactionTypeEnum::REVERSAL_IN,

            'transaction_date' =>
                $data->reversalDate,

            'sequence_no' =>
                $sequence,

            'referenceable_type' =>
                $data->referenceType,

            'referenceable_id' =>
                $data->referenceId,

            'reference_no' =>
                $data->referenceNo,

            'reversal_of_ledger_id' =>
                $ledger->id,

            'quantity_in' =>
                $reverseQuantity,

            'quantity_out' =>
                0,

            'unit_cost' =>
                (float) $ledger->unit_cost,

            'total_cost' =>
                (float) $ledger->total_cost,

            'balance_quantity' =>
                $newQuantity,

            'balance_average_cost' =>
                $newAverageCost,

            'remarks' =>
                $data->remarks,

            'created_by' =>
                userId(),
        ]);
    }
}