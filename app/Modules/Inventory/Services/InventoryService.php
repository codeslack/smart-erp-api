<?php

namespace App\Modules\Inventory\Services;

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
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $remarks = null
    ) {
        return $this->repository->stockOut(
            productId: $productId,
            warehouseId: $warehouseId,
            quantity: $quantity,
            transactionType: $transactionType,
            referenceType: $referenceType,
            referenceId: $referenceId,
            remarks: $remarks,
        );
    }
}
