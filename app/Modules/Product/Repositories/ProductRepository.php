<?php

namespace App\Modules\Product\Repositories;

use App\Modules\Product\Models\Product;
use App\Core\Repositories\BaseRepository;
use App\Modules\Product\Repositories\Contracts\ProductRepositoryInterface;

class ProductRepository
    extends BaseRepository
    implements ProductRepositoryInterface
{
    public function __construct(Product $model)
    {
        parent::__construct($model);
    }

    public function findBySku(string $sku)
    {
        return $this->model
            ->where('sku', $sku)
            ->first();
    }
}
