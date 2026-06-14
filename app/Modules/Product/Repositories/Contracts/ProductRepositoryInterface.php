<?php

namespace App\Modules\Product\Repositories\Contracts;

use App\Core\Contracts\BaseRepositoryInterface;

interface ProductRepositoryInterface
    extends BaseRepositoryInterface
{
    public function findBySku(string $sku);
}
