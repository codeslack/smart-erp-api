<?php

namespace App\Modules\Inventory\Repositories;

use App\Modules\Product\Models\Product;
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
        return $this->stockQuery(
            $productId,
            $productVariantId,
            $warehouseId
        )->first();
    }

    public function findForUpdate(
        int $productId,
        ?int $productVariantId,
        int $warehouseId
    ): ?ProductStock {
        $stock = $this->stockQuery(
            $productId,
            $productVariantId,
            $warehouseId
        )
            ->withTrashed()
            ->lockForUpdate()
            ->first();

        if ($stock?->trashed()) {
            $stock->restore();
        }

        return $stock;
    }

    public function getOrCreateForUpdate(
        int $productId,
        ?int $productVariantId,
        int $warehouseId
    ): ProductStock {
        /*
         * A missing stock row cannot be locked. Locking its parent
         * product serializes first-time stock creation for that product.
         * Existing stock movements continue to lock their stock row.
         */
        Product::query()
            ->whereKey($productId)
            ->lockForUpdate()
            ->firstOrFail();

        $stock = $this->stockQuery(
            $productId,
            $productVariantId,
            $warehouseId
        )
            ->withTrashed()
            ->lockForUpdate()
            ->first();

        if ($stock) {
            if ($stock->trashed()) {
                $stock->restore();
            }

            return $stock;
        }

        return $this->model
            ->newQuery()
            ->create([
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
                'warehouse_id' => $warehouseId,
                'quantity' => 0,
                'average_cost' => 0,
            ]);
    }

    public function updateBalance(
        ProductStock $stock,
        float $quantity,
        float $averageCost,
        \DateTimeInterface $movementDate
    ): ProductStock {
        $stock->update([
            'quantity' => $quantity,
            'average_cost' => $averageCost,
            'last_movement_at' => $movementDate,
        ]);

        return $stock->refresh();
    }

    protected function stockQuery(
        int $productId,
        ?int $productVariantId,
        int $warehouseId
    ) {
        return $this->model
            ->newQuery()
            ->where('product_id', $productId)
            ->when(
                $productVariantId !== null,
                fn ($query) => $query->where(
                    'product_variant_id',
                    $productVariantId
                ),
                fn ($query) => $query->whereNull(
                    'product_variant_id'
                )
            )
            ->where('warehouse_id', $warehouseId);
    }
}
