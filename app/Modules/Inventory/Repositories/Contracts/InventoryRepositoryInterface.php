<?php

namespace App\Modules\Inventory\Repositories\Contracts;

use App\Modules\Product\Models\Product;

interface InventoryRepositoryInterface
{
    public function openingStock(array $data);

    public function stock(Product $product);

    public function ledger(Product $product);

    public function stockIn(
        int $productId,
        int $warehouseId,
        float $quantity,
        string $transactionType,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $remarks = null
    );
}
