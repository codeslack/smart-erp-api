<?php

namespace App\Modules\Inventory\Services\Reversal;

use App\Core\Exceptions\BusinessException;

use App\Modules\Inventory\Models\StockLedger;
use App\Modules\Inventory\Data\InventoryReversalData;

use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;
use App\Modules\Inventory\Enums\InventoryCostLayerStatusEnum;

use App\Modules\Inventory\Services\InventorySettingsService;
use App\Modules\Inventory\Services\InventoryBatchService;
use App\Modules\Inventory\Services\InventorySerialService;

use App\Modules\Inventory\Repositories\Contracts\StockLedgerRepositoryInterface;
use App\Modules\Inventory\Repositories\Contracts\ProductStockRepositoryInterface;
use App\Modules\Inventory\Repositories\Contracts\InventoryCostLayerRepositoryInterface;
use App\Modules\Inventory\Repositories\Contracts\InventoryCostLayerConsumptionRepositoryInterface;

class InventoryStockOutReversalService
{
    public function __construct(
        protected InventorySettingsService $settings,
        protected StockLedgerRepositoryInterface $stockLedgerRepository,
        protected ProductStockRepositoryInterface $productStockRepository,
        protected InventoryCostLayerRepositoryInterface $layerRepository,
        protected InventoryCostLayerConsumptionRepositoryInterface $consumptionRepository,
        protected InventoryBatchService $batchService,
        protected InventorySerialService $serialService,
    ) {}

    public function reverse(
        StockLedger $ledger,
        InventoryReversalData $data
    ) {
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

        $reverseQuantity = (float) $ledger->quantity_out;

        if ($reverseQuantity <= 0) {
            throw new BusinessException(
                'Invalid stock-out quantity for reversal.',
                'INVALID_REVERSAL_QUANTITY'
            );
        }

        if ($this->settings->isFifo()) {
            $consumptions = $this->consumptionRepository
                ->findByStockLedgerId($ledger->id);

            if ($consumptions->isEmpty()) {
                throw new BusinessException(
                    'FIFO consumption records not found for stock-out ledger.',
                    'CONSUMPTION_NOT_FOUND'
                );
            }

            $consumedQuantity = 0.0;

            foreach ($consumptions as $consumption) {
                $quantity = (float) $consumption->quantity;

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

            foreach ($consumptions as $consumption) {
                $layer = $this->layerRepository->findForUpdate(
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
                        'remaining_quantity' => $restoredQuantity,
                        'status' => InventoryCostLayerStatusEnum::OPEN,
                    ]
                );
            }
        }

        /*
         * Restore the batch quantity consumed by the stock-out.
         */
        if ($ledger->product_batch_id !== null) {
            $batch = $this->batchService->lock(
                (int) $ledger->product_batch_id
            );

            $this->batchService->increase(
                $batch,
                $reverseQuantity
            );
        }

        /*
         * Restore a serial sold by the original stock-out.
         */
        if ($ledger->product_serial_id !== null) {
            $serial = $this->serialService->lock(
                (int) $ledger->product_serial_id
            );

            $this->serialService->markAvailable($serial);
        }

        $currentQuantity = (float) $stock->quantity;
        $newQuantity = $currentQuantity + $reverseQuantity;

        $newAverageCost = (float) $stock->average_cost;

        if ($this->settings->isWeightedAverage()) {
            $currentValue =
                $currentQuantity * (float) $stock->average_cost;

            $restoredValue = (float) $ledger->total_cost;
            $newValue = $currentValue + $restoredValue;

            $newAverageCost = $newQuantity > 0
                ? $newValue / $newQuantity
                : 0.0;
        }

        $this->productStockRepository->updateBalance(
            stock: $stock,
            quantity: $newQuantity,
            averageCost: $newAverageCost,
            movementDate: $data->reversalDate
        );

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
            'transaction_type' => InventoryTransactionTypeEnum::REVERSAL_IN,
            'transaction_date' => $data->reversalDate,
            'sequence_no' => $sequence,
            'referenceable_type' => $data->referenceType,
            'referenceable_id' => $data->referenceId,
            'reference_no' => $data->referenceNo,
            'reversal_of_ledger_id' => $ledger->id,
            'quantity_in' => $reverseQuantity,
            'quantity_out' => 0,
            'unit_cost' => (float) $ledger->unit_cost,
            'total_cost' => (float) $ledger->total_cost,
            'balance_quantity' => $newQuantity,
            'balance_average_cost' => $newAverageCost,
            'remarks' => $data->remarks,
            'created_by' => userId(),
        ]);
    }
}