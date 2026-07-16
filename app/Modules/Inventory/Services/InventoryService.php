<?php

namespace App\Modules\Inventory\Services;

use DomainException;
use App\Modules\Product\Models\Product;
use App\Modules\Inventory\Repositories\Contracts\InventoryRepositoryInterface;

class InventoryService
{
    public function __construct(
        protected InventoryRepositoryInterface $repository
    ) {}

    public function openingStock(array $data)
    {
        return $this->repository
            ->openingStock($data);
    }

    public function stock(Product $product)
    {
        return $this->repository
            ->stock($product);
    }

    public function ledger(Product $product)
    {
        return $this->repository
            ->ledger($product);
    }

    public function stockReport(
        array $filters = []
    ) {
        return $this->repository
            ->stockReport($filters);
    }    

    public function stockIn(
        int $productId,
        int $warehouseId,
        float $quantity,
        float $unitCost,
        string $transactionType,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $remarks = null
    ) {
        return $this->repository->stockIn(
            productId: $productId,
            warehouseId: $warehouseId,
            quantity: $quantity,
            unitCost: $unitCost,
            transactionType: $transactionType,
            referenceType: $referenceType,
            referenceId: $referenceId,
            remarks: $remarks,
        );
    }

    public function stockOut(
        int $productId,
        int $warehouseId,
        float $quantity,
        string $transactionType,
        ?float $unitCost = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $remarks = null
    ) {
        return $this->repository->stockOut(
            productId: $productId,
            warehouseId: $warehouseId,
            quantity: $quantity,
            transactionType: $transactionType,
            unitCost: $unitCost,
            referenceType: $referenceType,
            referenceId: $referenceId,
            remarks: $remarks,
        );
    }

    /**
     * Get current available stock.
     */
    public function availableStock(
        int $productId,
        int $warehouseId
    ): float {

        return $this->repository
            ->availableStock(
                $productId,
                $warehouseId
            );
    }

    /**
     * Check stock availability.
     */
    public function hasEnoughStock(
        int $productId,
        int $warehouseId,
        float $requiredQuantity
    ): bool {

        return $this->availableStock(
            $productId,
            $warehouseId
        ) >= $requiredQuantity;
    }

    /**
     * Validate stock and throw exception if insufficient.
     */
    public function validateStockOrFail(
        int $productId,
        int $warehouseId,
        float $requiredQuantity,
        ?string $productName = null
    ): void {

        $availableQuantity = $this->availableStock(
            $productId,
            $warehouseId
        );

        if ($availableQuantity < $requiredQuantity) {

            throw new DomainException(
                sprintf(
                    '%s stock is insufficient. Available: %s, Requested: %s',
                    $productName ?? 'Product',
                    $availableQuantity,
                    $requiredQuantity
                )
            );
        }
    }

    /**
     * Get current average cost.
     */
    public function averageCost(
        int $productId,
        int $warehouseId
    ): float {

        return $this->repository->averageCost(
            $productId,
            $warehouseId
        );
    }
}