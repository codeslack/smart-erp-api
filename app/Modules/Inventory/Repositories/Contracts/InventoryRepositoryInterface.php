<?php

namespace App\Modules\Inventory\Repositories\Contracts;

use App\Modules\Product\Models\Product;

interface InventoryRepositoryInterface
{
    public function openingStock(array $data);

    public function stock(Product $product);

    public function ledger(Product $product);

    public function stockReport(
        array $filters = []
    );
    
    public function availableStock(
        int $productId,
        int $warehouseId
    ): float;

    public function averageCost(
        int $productId,
        int $warehouseId
    ): float;

    public function stockIn(
        int $productId,
        int $warehouseId,
        float $quantity,
        float $unitCost,
        string $transactionType,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $remarks = null
    );

    public function stockOut(
        int $productId,
        int $warehouseId,
        float $quantity,
        string $transactionType,
        ?float $unitCost = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $remarks = null
    );
}
