<?php

namespace App\Modules\Inventory\Repositories;

use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Repositories\Contracts\ProductStockRepositoryInterface;

class ProductStockRepository
    implements ProductStockRepositoryInterface
{
    public function __construct(
        protected ProductStock $model
    ) {}

    public function find(
        int $productId,
        ?int $productVariantId,
        int $warehouseId
    ): ?ProductStock {

        return $this->model
            ->newQuery()
            ->where(
                'product_id',
                $productId
            )
            ->when(
                $productVariantId !== null,
                fn ($query) =>
                    $query->where(
                        'product_variant_id',
                        $productVariantId
                    ),
                fn ($query) =>
                    $query->whereNull(
                        'product_variant_id'
                    )
            )
            ->where(
                'warehouse_id',
                $warehouseId
            )
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Find Stock For Update
    |--------------------------------------------------------------------------
    */

    public function findForUpdate(
        int $productId,
        ?int $productVariantId,
        int $warehouseId
    ): ?ProductStock {

        return $this->model
            ->newQuery()
            ->where(
                'product_id',
                $productId
            )
            ->when(
                $productVariantId !== null,
                fn ($query) =>
                    $query->where(
                        'product_variant_id',
                        $productVariantId
                    ),
                fn ($query) =>
                    $query->whereNull(
                        'product_variant_id'
                    )
            )
            ->where(
                'warehouse_id',
                $warehouseId
            )
            ->lockForUpdate()
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Get Or Create Stock For Update
    |--------------------------------------------------------------------------
    */

    public function getOrCreateForUpdate(
        int $productId,
        ?int $productVariantId,
        int $warehouseId
    ): ProductStock {

        $stock = $this->findForUpdate(
            productId: $productId,
            productVariantId: $productVariantId,
            warehouseId: $warehouseId
        );

        if ($stock) {
            return $stock;
        }

        return $this->model
            ->newQuery()
            ->create([
                'product_id' =>
                    $productId,

                'product_variant_id' =>
                    $productVariantId,

                'warehouse_id' =>
                    $warehouseId,

                'quantity' =>
                    0,

                'average_cost' =>
                    0,
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Update Balance
    |--------------------------------------------------------------------------
    */

    public function updateBalance(
        ProductStock $stock,
        float $quantity,
        float $averageCost,
        \DateTimeInterface $movementDate
    ): ProductStock {

        $stock->update([
            'quantity' =>
                $quantity,

            'average_cost' =>
                $averageCost,

            'last_movement_at' =>
                $movementDate,
        ]);

        return $stock->refresh();
    }
}