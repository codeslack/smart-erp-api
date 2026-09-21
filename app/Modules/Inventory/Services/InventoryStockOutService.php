<?php

namespace App\Modules\Inventory\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

use App\Core\Exceptions\BusinessException;

use App\Modules\Product\Models\Product;

use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\StockLedger;

use App\Modules\Inventory\Repositories\Contracts\ProductStockRepositoryInterface;

class InventoryStockOutService
{
    public function __construct(
        protected InventorySettingsService $settings,
        protected FifoCostingService $fifoCosting,
        protected WeightedAverageCostingService $weightedAverageCosting,
        protected ProductStockRepositoryInterface $productStockRepository,
        protected InventoryMovementValidator $validator,
        protected InventoryLedgerService $ledgerService,
        protected InventoryBatchService $batchService,
        protected InventorySerialService $serialService,
    ) {}

    public function post(
        InventoryMovementData $movement
    ): StockLedger {
        return DB::transaction(
            function () use ($movement) {

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

                $stock =
                    $this->getStock(
                        $movement
                    );

                $currentQuantity =
                    (float) $stock->quantity;

                $newQuantity =
                    $currentQuantity
                    - $movement->quantity;

                $this->validateAvailableStock(
                    currentQuantity: $currentQuantity,
                    newQuantity: $newQuantity,
                );

                $costResult =
                    $this->calculateCost(
                        movement: $movement,
                        stock: $stock,
                    );

                $newAverageCost =
                    (float) $stock->average_cost;

                if (
                    bccomp(
                        (string) $newQuantity,
                        '0',
                        4
                    ) === 0
                ) {
                    $newAverageCost = 0.0;
                }

                $this->productStockRepository
                    ->updateBalance(
                        stock: $stock,
                        quantity: $newQuantity,
                        averageCost: $newAverageCost,
                        movementDate:
                            $movement->transactionDate,
                    );

                $this->decreaseBatch(
                    $movement
                );

                $this->sellSerial(
                    $movement
                );

                $ledger =
                    $this->ledgerService->create(
                        movement: $movement,
                        quantityIn: 0,
                        quantityOut: $movement->quantity,
                        unitCost: $costResult['unit_cost'],
                        totalCost: $costResult['total_cost'],
                        balanceQuantity: $newQuantity,
                        balanceAverageCost: $newAverageCost,
                    );

                $this->recordFifoConsumption(
                    $ledger,
                    $costResult['layers']
                );

                return $ledger;
            }
        );
    }

    protected function getStock(
        InventoryMovementData $movement
    ): ProductStock {
        $stock =
            $this->productStockRepository
                ->findForUpdate(
                    productId: $movement->productId,
                    productVariantId: $movement->productVariantId,
                    warehouseId: $movement->warehouseId,
                );

        if ($stock !== null) {
            return $stock;
        }

        if (
            ! $this->settings->allowNegativeStock()
        ) {
            throw new BusinessException(
                'Insufficient stock.',
                'INSUFFICIENT_STOCK'
            );
        }

        return $this->productStockRepository
            ->getOrCreateForUpdate(
                productId: $movement->productId,
                productVariantId: $movement->productVariantId,
                warehouseId: $movement->warehouseId,
            );
    }

    protected function validateAvailableStock(
        float $currentQuantity,
        float $newQuantity
    ): void {
        if (
            $newQuantity >= 0
            || $this->settings->allowNegativeStock()
        ) {
            return;
        }

        throw new BusinessException(
            sprintf(
                'Insufficient stock. Available: %s, Requested: %s.',
                number_format(
                    $currentQuantity,
                    4,
                    '.',
                    ''
                ),
                number_format(
                    $newQuantity + $currentQuantity,
                    4,
                    '.',
                    ''
                )
            ),
            'INSUFFICIENT_STOCK'
        );
    }

    protected function calculateCost(
        InventoryMovementData $movement,
        ProductStock $stock
    ): array {
        if ($this->settings->isFifo()) {
            $result =
                $this->fifoCosting
                    ->consumeAndCalculateCost(
                        $movement
                    );

            $totalCost =
                (float) $result['total_cost'];

            $unitCost =
                $movement->quantity > 0
                    ? $totalCost / $movement->quantity
                    : 0.0;

            return [
                'total_cost' => $totalCost,
                'unit_cost' => $unitCost,
                'layers' => $result['layers'],
            ];
        }

        $averageCost =
            (float) $stock->average_cost;

        $totalCost =
            $this->weightedAverageCosting
                ->calculateOutgoingCost(
                    quantity: $movement->quantity,
                    averageCost: $averageCost,
                );

        return [
            'total_cost' => $totalCost,
            'unit_cost' => $averageCost,
            'layers' => collect(),
        ];
    }

    protected function decreaseBatch(
        InventoryMovementData $movement
    ): void {
        if ($movement->batchId === null) {
            return;
        }

        $batch =
            $this->batchService->lock(
                $movement->batchId
            );

        $this->batchService->decrease(
            $batch,
            $movement->quantity
        );
    }

    protected function sellSerial(
        InventoryMovementData $movement
    ): void {
        if ($movement->serialId === null) {
            return;
        }

        $serial =
            $this->serialService->lockForStockOut(
                serialId: $movement->serialId,
                productId: $movement->productId,
                warehouseId: $movement->warehouseId,
                productVariantId: $movement->productVariantId,
                productBatchId: $movement->batchId,
            );

        $this->serialService->markSold(
            $serial
        );
    }

    protected function recordFifoConsumption(
        StockLedger $ledger,
        Collection $layers
    ): void {
        if (
            ! $this->settings->isFifo()
            || $layers->isEmpty()
        ) {
            return;
        }

        $this->fifoCosting
            ->recordConsumptions(
                stockLedgerId: $ledger->id,
                consumptions: $layers,
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