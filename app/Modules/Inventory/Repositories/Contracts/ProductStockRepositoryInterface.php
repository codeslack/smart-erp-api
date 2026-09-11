<?php

namespace App\Modules\Inventory\Repositories\Contracts;

use App\Modules\Inventory\Models\ProductStock;

interface ProductStockRepositoryInterface
{
    public function find(
        int $productId,
        ?int $productVariantId,
        int $warehouseId
    ): ?ProductStock;

    public function findForUpdate(
        int $productId,
        ?int $productVariantId,
        int $warehouseId
    ): ?ProductStock;

    public function getOrCreateForUpdate(
        int $productId,
        ?int $productVariantId,
        int $warehouseId
    ): ProductStock;

    public function updateBalance(
        ProductStock $stock,
        float $quantity,
        float $averageCost,
        \DateTimeInterface $movementDate
    ): ProductStock;
}