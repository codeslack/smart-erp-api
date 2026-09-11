<?php

namespace App\Modules\Inventory\Services;

use App\Core\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;

use App\Modules\Product\Models\Product;

use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\StockLedger;

use App\Modules\Inventory\Repositories\Contracts\ProductStockRepositoryInterface;
use App\Modules\Inventory\Repositories\Contracts\StockLedgerRepositoryInterface;

class InventoryPostingService
{
    public function __construct(
        protected InventorySettingsService $settings,

        protected FifoCostingService $fifoCosting,

        protected WeightedAverageCostingService $weightedAverageCosting,

        protected ProductStockRepositoryInterface $productStockRepository,

        protected StockLedgerRepositoryInterface $stockLedgerRepository,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Stock In
    |--------------------------------------------------------------------------
    |
    | Purchase
    | Opening Stock
    | Sales Return
    | Stock Adjustment +
    | Stock Transfer IN
    |
    */

    public function stockIn(
        InventoryMovementData $movement
    ): StockLedger {

        logger()->info(
            "meg",
            [
                app( \App\Modules\Inventory\Services\InventorySettingsService::class )->costingMethod()
            ]
        );
        return DB::transaction(
            function () use ($movement) {

                $this->validateMovement(
                    $movement
                );

                $product = $this->getProduct(
                    $movement
                );

                $this->validateProduct(
                    $product
                );

                /*
                |--------------------------------------------------------------------------
                | Product Stock
                |--------------------------------------------------------------------------
                */

                $stock =
                    $this->productStockRepository
                        ->getOrCreateForUpdate(
                            productId:
                                $movement->productId,

                            productVariantId:
                                $movement->productVariantId,

                            warehouseId:
                                $movement->warehouseId
                        );

                /*
                |--------------------------------------------------------------------------
                | Existing Stock
                |--------------------------------------------------------------------------
                */

                $currentQuantity =
                    (float) $stock->quantity;

                $currentAverageCost =
                    (float) $stock->average_cost;

                /*
                |--------------------------------------------------------------------------
                | Weighted Average
                |--------------------------------------------------------------------------
                |
                | ProductStock always maintains the current
                | inventory valuation average.
                |
                */

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
                                $movement->unitCost
                        );

                $newQuantity =
                    $currentQuantity
                    + $movement->quantity;

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
                            $movement->transactionDate
                    );

                /*
                |--------------------------------------------------------------------------
                | Stock Ledger
                |--------------------------------------------------------------------------
                */

                $ledger =
                    $this->createLedger(
                        movement:
                            $movement,

                        quantityIn:
                            $movement->quantity,

                        quantityOut:
                            0,

                        unitCost:
                            $movement->unitCost,

                        totalCost:
                            $movement->quantity
                            * $movement->unitCost,

                        balanceQuantity:
                            $newQuantity,

                        balanceAverageCost:
                            $newAverageCost
                    );

                /*
                |--------------------------------------------------------------------------
                | FIFO Layer
                |--------------------------------------------------------------------------
                |
                | Every stock IN creates one FIFO cost layer.
                |
                */

                if (
                    $this->settings->isFifo()
                ) {

                    $this->fifoCosting
                        ->createLayer(
                            movement:
                                $movement,

                            stockLedgerId:
                                $ledger->id
                        );
                }

                return $ledger;
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Stock Out
    |--------------------------------------------------------------------------
    |
    | Sale
    | Purchase Return
    | Stock Adjustment -
    | Stock Transfer OUT
    |
    */

    public function stockOut(
        InventoryMovementData $movement
    ): StockLedger {

        return DB::transaction(
            function () use ($movement) {

                $this->validateMovement(
                    $movement
                );

                $product = $this->getProduct(
                    $movement
                );

                $this->validateProduct(
                    $product
                );

                /*
                |--------------------------------------------------------------------------
                | Product Stock
                |--------------------------------------------------------------------------
                */

                $stock =
                    $this->productStockRepository
                        ->findForUpdate(
                            productId:
                                $movement->productId,

                            productVariantId:
                                $movement->productVariantId,

                            warehouseId:
                                $movement->warehouseId
                        );

                /*
                |--------------------------------------------------------------------------
                | Stock Does Not Exist
                |--------------------------------------------------------------------------
                */

                if (! $stock) {

                    if (
                        ! $this->settings
                            ->allowNegativeStock()
                    ) {

                        throw new BusinessException(
                            'Insufficient stock.',
                            'INSUFFICIENT_STOCK'
                        );
                    }

                    $stock =
                        $this->productStockRepository
                            ->getOrCreateForUpdate(
                                productId:
                                    $movement->productId,

                                productVariantId:
                                    $movement->productVariantId,

                                warehouseId:
                                    $movement->warehouseId
                            );
                }

                /*
                |--------------------------------------------------------------------------
                | Current Quantity
                |--------------------------------------------------------------------------
                */

                $currentQuantity =
                    (float) $stock->quantity;

                $newQuantity =
                    $currentQuantity
                    - $movement->quantity;

                /*
                |--------------------------------------------------------------------------
                | Negative Stock Validation
                |--------------------------------------------------------------------------
                */

                if (
                    $newQuantity < 0
                    &&
                    ! $this->settings
                        ->allowNegativeStock()
                ) {

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
                                $movement->quantity,
                                4,
                                '.',
                                ''
                            )
                        ),
                        'INSUFFICIENT_STOCK'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Calculate Outgoing Cost
                |--------------------------------------------------------------------------
                */

                $costResult =
                    $this->calculateOutgoingCost(
                        movement:
                            $movement,

                        stock:
                            $stock
                    );

                $totalCost =
                    $costResult['total_cost'];

                $unitCost =
                    $costResult['unit_cost'];

                /*
                |--------------------------------------------------------------------------
                | Average Cost
                |--------------------------------------------------------------------------
                |
                | Outgoing movements do not change weighted
                | average cost while stock remains.
                |
                | When quantity reaches zero, reset average cost.
                |
                */

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
                            $movement->transactionDate
                    );

                /*
                |--------------------------------------------------------------------------
                | Stock Ledger
                |--------------------------------------------------------------------------
                */

                $ledger = $this->createLedger(
                    movement:
                        $movement,

                    quantityIn:
                        0,

                    quantityOut:
                        $movement->quantity,

                    unitCost:
                        $unitCost,

                    totalCost:
                        $totalCost,

                    balanceQuantity:
                        $newQuantity,

                    balanceAverageCost:
                        $newAverageCost
                );

                if (
                    $this->settings->isFifo()
                    && $costResult['layers']->isNotEmpty()
                ) {
                    $this->fifoCosting
                        ->recordConsumptions(
                            stockLedgerId:
                                $ledger->id,

                            consumptions:
                                $costResult['layers']
                        );
                }

                return $ledger;
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Calculate Outgoing Cost
    |--------------------------------------------------------------------------
    */

    protected function calculateOutgoingCost(
        InventoryMovementData $movement,
        ProductStock $stock
    ): array {

        /*
        |--------------------------------------------------------------------------
        | FIFO
        |--------------------------------------------------------------------------
        */

        if (
            $this->settings->isFifo()
        ) {

            $result =
                $this->fifoCosting
                    ->consumeAndCalculateCost(
                        $movement
                    );

            $totalCost =
                (float) $result['total_cost'];

            $unitCost =
                $movement->quantity > 0
                    ? $totalCost
                        / $movement->quantity
                    : 0;

            return [
                'total_cost' =>
                    $totalCost,

                'unit_cost' =>
                    $unitCost,

                'layers' =>
                    $result['layers'],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Weighted Average
        |--------------------------------------------------------------------------
        */

        $averageCost =
            (float) $stock->average_cost;

        $totalCost =
            $this->weightedAverageCosting
                ->calculateOutgoingCost(
                    quantity:
                        $movement->quantity,

                    averageCost:
                        $averageCost
                );

        return [
            'total_cost' =>
                $totalCost,

            'unit_cost' =>
                $averageCost,

            'layers' =>
                collect(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Create Stock Ledger
    |--------------------------------------------------------------------------
    */

    protected function createLedger(
        InventoryMovementData $movement,
        float $quantityIn,
        float $quantityOut,
        float $unitCost,
        float $totalCost,
        float $balanceQuantity,
        float $balanceAverageCost
    ): StockLedger {

        $sequenceNumber =
            $this->stockLedgerRepository
                ->nextSequenceNumber(
                    productId:
                        $movement->productId,

                    productVariantId:
                        $movement->productVariantId,

                    warehouseId:
                        $movement->warehouseId
                );

        return $this->stockLedgerRepository
            ->create([
                'product_id' =>
                    $movement->productId,

                'product_variant_id' =>
                    $movement->productVariantId,

                'warehouse_id' =>
                    $movement->warehouseId,

                'product_batch_id' =>
                    $movement->batchId,

                'product_serial_id' =>
                    $movement->serialId,

                'transaction_type' =>
                    $movement->transactionType,

                'transaction_date' =>
                    $movement->transactionDate,

                'sequence_no' =>
                    $sequenceNumber,

                'referenceable_type' =>
                    $movement->referenceType,

                'referenceable_id' =>
                    $movement->referenceId,

                'reference_no' =>
                    $movement->referenceNo,

                'quantity_in' =>
                    $quantityIn,

                'quantity_out' =>
                    $quantityOut,

                'unit_cost' =>
                    $unitCost,

                'total_cost' =>
                    $totalCost,

                'balance_quantity' =>
                    $balanceQuantity,

                'balance_average_cost' =>
                    $balanceAverageCost,

                'remarks' =>
                    $movement->remarks,

                'created_by' =>
                    userId(),
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Product
    |--------------------------------------------------------------------------
    */

    protected function getProduct(
        InventoryMovementData $movement
    ): Product {

        return Product::query()
            ->findOrFail(
                $movement->productId
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Product Validation
    |--------------------------------------------------------------------------
    */

    protected function validateProduct(
        Product $product
    ): void {

        if (! $product->requiresStock()) {

            throw new BusinessException(
                'This product does not support inventory stock.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Movement Validation
    |--------------------------------------------------------------------------
    */

    protected function validateMovement(
        InventoryMovementData $movement
    ): void {

        if (
            $movement->quantity <= 0
        ) {

            throw new BusinessException(
                'Movement quantity must be greater than zero.'
            );
        }

        if (
            $movement->unitCost < 0
        ) {

            throw new BusinessException(
                'Unit cost cannot be negative.'
            );
        }

        if (
            blank($movement->transactionType)
        ) {

            throw new BusinessException(
                'Transaction type is required.'
            );
        }
    }
}