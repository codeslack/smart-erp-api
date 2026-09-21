<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Models\StockLedger;

use App\Modules\Inventory\Repositories\Contracts\StockLedgerRepositoryInterface;

class InventoryLedgerService
{
    public function __construct(
        protected StockLedgerRepositoryInterface $repository,
    ) {}

    public function create(
        InventoryMovementData $movement,
        float $quantityIn,
        float $quantityOut,
        float $unitCost,
        float $totalCost,
        float $balanceQuantity,
        float $balanceAverageCost,
    ): StockLedger {
        $sequenceNumber =
            $this->repository
                ->nextSequenceNumber(
                    productId:
                        $movement->productId,

                    productVariantId:
                        $movement->productVariantId,

                    warehouseId:
                        $movement->warehouseId,
                );

        return $this->repository->create([
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
}