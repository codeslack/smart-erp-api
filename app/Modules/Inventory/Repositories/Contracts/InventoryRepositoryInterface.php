<?php

namespace App\Modules\Inventory\Repositories\Contracts;

use App\Modules\Product\Models\Product;

interface InventoryRepositoryInterface
{
    public function openingStock(array $data);

    public function stock(Product $product);

    public function ledger(Product $product);
}