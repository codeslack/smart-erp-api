<?php

namespace App\Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;

use App\Modules\Product\Models\Product;

use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Models\StockLedger;

use App\Modules\Inventory\Repositories\Contracts\ProductStockRepositoryInterface;

class InventoryStockInService
{
    public function __construct(
        protected InventorySettingsService $settings,
        protected WeightedAverageCostingService $weightedAverageCosting,
        protected FifoCostingService $fifoCosting,
        protected ProductStockRepositoryInterface $productStockRepository,
        protected InventoryMovementValidator $validator,
        protected InventoryLedgerService $ledgerService,
        protected InventoryBatchService $batchService,
        protected InventorySerialService $serialService,
    ) {}

    /**
     * Validate a stock-in movement before inventory
     * state is changed.
     *
     * This method is also used by Opening Stock,
     * where batch/serial activation must happen only
     * after the movement has passed validation.
     */
    public function validate(
        InventoryMovementData $movement
    ): void {
        $this->validator->validate(
            $movement
        );

        $product =
            $this->getProduct(
                $movement
            );

        $this->validator->validateProduct(
            $product
        );

        $this->validator->validateTracking(
            $product,
            $movement
        );

        $this->validator->validateStockInSerial(
            $movement
        );
    }

    public function post(
        InventoryMovementData $movement
    ): StockLedger {
        return DB::transaction(
            function () use ($movement) {

                $this->validate(
                    $movement
                );

                $this->lockSerial(
                    $movement
                );

                $stock =
                    $this->productStockRepository
                        ->getOrCreateForUpdate(
                            productId:
                                $movement->productId,
                            productVariantId:
                                $movement->productVariantId,
                            warehouseId:
                                $movement->warehouseId,
                        );

                $currentQuantity =
                    (float) $stock->quantity;

                $currentAverageCost =
                    (float) $stock->average_cost;

                $newAverageCost =
                    $this->weightedAverageCosting
                        ->calculateAverageCost(
                            currentQuantity:
                                $currentQuantity,
                            currentAverageCost:
                                $currentAverageCost,
                            incomingQuantity:
                                $movement->quantity,
                            incomingUnitCost:
                                $movement->unitCost,
                        );

                $newQuantity =
                    $currentQuantity
                    + $movement->quantity;

                $this->productStockRepository
                    ->updateBalance(
                        stock: $stock,
                        quantity: $newQuantity,
                        averageCost: $newAverageCost,
                        movementDate:
                            $movement->transactionDate,
                    );

                $this->increaseBatch(
                    $movement
                );

                $ledger =
                    $this->ledgerService->create(
                        movement: $movement,
                        quantityIn: $movement->quantity,
                        quantityOut: 0,
                        unitCost: $movement->unitCost,
                        totalCost:
                            $movement->quantity
                            * $movement->unitCost,
                        balanceQuantity: $newQuantity,
                        balanceAverageCost: $newAverageCost,
                    );

                $this->createFifoLayer(
                    $movement,
                    $ledger
                );

                return $ledger;
            }
        );
    }

    protected function lockSerial(
        InventoryMovementData $movement
    ): void {
        if ($movement->serialId === null) {
            return;
        }

        $this->serialService->lockForStockIn(
            serialId: $movement->serialId,
            productId: $movement->productId,
            warehouseId: $movement->warehouseId,
            productVariantId: $movement->productVariantId,
            productBatchId: $movement->batchId,
        );
    }

    protected function increaseBatch(
        InventoryMovementData $movement
    ): void {
        if ($movement->batchId === null) {
            return;
        }

        $batch =
            $this->batchService->lock(
                $movement->batchId
            );

        $this->batchService->increase(
            $batch,
            $movement->quantity
        );
    }

    protected function createFifoLayer(
        InventoryMovementData $movement,
        StockLedger $ledger
    ): void {
        if (! $this->settings->isFifo()) {
            return;
        }

        $this->fifoCosting
            ->createLayer(
                movement: $movement,
                stockLedgerId: $ledger->id,
            );
    }

    protected function getProduct(
        InventoryMovementData $movement
    ): Product {
        return Product::query()
            ->findOrFail(
                $movement->productId
            );
    }
}