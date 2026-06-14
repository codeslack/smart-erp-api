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
}