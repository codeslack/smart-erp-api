<?php

namespace App\Modules\ProductUnit\Repositories;

use Illuminate\Database\Eloquent\Collection;

use App\Core\Repositories\BaseRepository;

use App\Modules\ProductUnit\Models\ProductUnit;
use App\Modules\ProductUnit\Repositories\Contracts\ProductUnitRepositoryInterface;

class ProductUnitRepository extends BaseRepository
    implements ProductUnitRepositoryInterface
{
    public function __construct(
        ProductUnit $model
    ) {
        parent::__construct($model);
    }

    /*
    |--------------------------------------------------------------------------
    | Product UOMs
    |--------------------------------------------------------------------------
    */

    public function getByProductId(
        int $productId
    ): Collection {
        return $this->model
            ->newQuery()
            ->where('product_id', $productId)
            ->with('unit')
            ->orderBy('id')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Specific Product / Unit
    |--------------------------------------------------------------------------
    */

    public function findByProductAndUnit(
        int $productId,
        int $unitId
    ): ?ProductUnit {
        return $this->model
            ->newQuery()
            ->where('product_id', $productId)
            ->where('unit_id', $unitId)
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Purchase Units
    |--------------------------------------------------------------------------
    */

    public function getPurchaseUnits(
        int $productId
    ): Collection {
        return $this->model
            ->newQuery()
            ->where('product_id', $productId)
            ->where('is_purchase_unit', true)
            ->where('is_active', true)
            ->with('unit')
            ->orderBy('id')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Sales Units
    |--------------------------------------------------------------------------
    */

    public function getSalesUnits(
        int $productId
    ): Collection {
        return $this->model
            ->newQuery()
            ->where('product_id', $productId)
            ->where('is_sales_unit', true)
            ->where('is_active', true)
            ->with('unit')
            ->orderBy('id')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Base Unit
    |--------------------------------------------------------------------------
    */

    public function findBaseUnit(
        int $productId
    ): ?ProductUnit {
        return $this->model
            ->newQuery()
            ->where('product_id', $productId)
            ->whereHas(
                'product',
                fn ($query) => $query->whereColumn(
                    'products.unit_id',
                    'product_units.unit_id'
                )
            )
            ->with('unit')
            ->first();
    }
}