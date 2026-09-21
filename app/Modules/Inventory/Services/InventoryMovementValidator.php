<?php

namespace App\Modules\Inventory\Services;

use App\Core\Exceptions\BusinessException;
use App\Modules\Product\Models\Product;
use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Repositories\Contracts\StockLedgerRepositoryInterface;

class InventoryMovementValidator
{
    public function __construct(
        protected StockLedgerRepositoryInterface $stockLedgerRepository,
    ) {}

    /**
     * Validate common inventory movement rules.
     */
    public function validate(
        InventoryMovementData $movement
    ): void {
        $this->validateQuantity(
            $movement
        );

        $this->validateUnitCost(
            $movement
        );

        $this->validateTransactionType(
            $movement
        );
    }

    /**
     * Validate that the product can participate in inventory.
     */
    public function validateProduct(
        Product $product
    ): void {
        if (! $product->requiresStock()) {
            throw new BusinessException(
                'This product does not support inventory stock.'
            );
        }
    }

    /**
     * Validate batch/serial tracking rules.
     *
     * A product supports only one tracking mode:
     *
     * NONE
     * BATCH
     * SERIAL
     */
    public function validateTracking(
        Product $product,
        InventoryMovementData $movement
    ): void {
        /*
         * SERIAL product cannot receive/use a batch.
         */
        if (
            $product->isSerialTracked()
            && $movement->batchId !== null
        ) {
            throw new BusinessException(
                'Batch is not supported for serial-tracked products.'
            );
        }

        /*
         * BATCH product cannot receive/use a serial.
         */
        if (
            $product->isBatchTracked()
            && $movement->serialId !== null
        ) {
            throw new BusinessException(
                'Serial is not supported for batch-tracked products.'
            );
        }

        /*
         * NONE-tracked product cannot use batch or serial.
         */
        if (
            ! $product->hasInventoryTracking()
            && (
                $movement->batchId !== null
                || $movement->serialId !== null
            )
        ) {
            throw new BusinessException(
                'Batch or serial tracking is not supported for this product.'
            );
        }

        /*
         * When a specific serial is supplied, it represents
         * exactly one physical unit.
         *
         * SERIAL product + quantity 5 + serialId NULL
         * is still valid according to the inventory rules.
         */
        if (
            $product->isSerialTracked()
            && $movement->serialId !== null
            && (float) $movement->quantity !== 1.0
        ) {
            throw new BusinessException(
                'Serial tracked products must use quantity 1 when a serial is specified.'
            );
        }
    }

    /**
     * Validate serial uniqueness for STOCK IN operations.
     *
     * This must NOT be called for stock-out operations because
     * an existing available serial is exactly what stock-out needs.
     */
    public function validateStockInSerial(
        InventoryMovementData $movement
    ): void {
        if ($movement->serialId === null) {
            return;
        }

        if (
            $this->stockLedgerRepository
            ->hasActiveStockInForSerial(
                $movement->serialId
            )
        ) {
            throw new BusinessException(
                'Serial number is already in stock.',
                'SERIAL_ALREADY_IN_STOCK'
            );
        }
    }

    /**
     * Validate movement quantity.
     */
    protected function validateQuantity(
        InventoryMovementData $movement
    ): void {
        if ($movement->quantity <= 0) {
            throw new BusinessException(
                'Movement quantity must be greater than zero.'
            );
        }
    }

    /**
     * Validate movement unit cost.
     */
    protected function validateUnitCost(
        InventoryMovementData $movement
    ): void {
        if ($movement->unitCost < 0) {
            throw new BusinessException(
                'Unit cost cannot be negative.'
            );
        }
    }

    /**
     * Validate transaction type.
     */
    protected function validateTransactionType(
        InventoryMovementData $movement
    ): void {
        if (blank($movement->transactionType)) {
            throw new BusinessException(
                'Transaction type is required.'
            );
        }
    }
}
