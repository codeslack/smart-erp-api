<?php

namespace App\Modules\Inventory\Repositories;

use Illuminate\Database\QueryException;

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
        return $this->identityQuery(
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
        $stock = $this->identityQuery(
            $productId,
            $productVariantId,
            $warehouseId,
            withTrashed: true
        )
            ->lockForUpdate()
            ->first();

        // A stock balance is historical inventory state, not a disposable
        // record. Reuse a soft-deleted identity instead of inserting a
        // second balance row for the same product/variant/warehouse.
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
        // Use a non-locking existence read first. Locking an absent row can
        // acquire a gap lock and make simultaneous first inserts deadlock.
        $existing = $this->identityQuery(
            $productId,
            $productVariantId,
            $warehouseId,
            withTrashed: true
        )->first();

        if ($existing !== null) {
            $stock = $this->findForUpdate(
                $productId,
                $productVariantId,
                $warehouseId
            );

            if ($stock !== null) {
                return $stock;
            }
        }

        try {
            return $this->model
                ->newQuery()
                ->create([
                    'product_id' => $productId,
                    'product_variant_id' => $productVariantId,
                    'warehouse_id' => $warehouseId,
                    'quantity' => 0,
                    'average_cost' => 0,
                ]);
        } catch (QueryException $exception) {
            // A concurrent transaction may have inserted the same identity.
            // Re-read under lock; only suppress the error if that exact row
            // now exists. Unrelated database errors must still propagate.
            $stock = $this->findForUpdate(
                $productId,
                $productVariantId,
                $warehouseId
            );

            if ($stock !== null) {
                return $stock;
            }

            throw $exception;
        }
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

    protected function identityQuery(
        int $productId,
        ?int $productVariantId,
        int $warehouseId,
        bool $withTrashed = false
    ) {
        $query = $this->model->newQuery();

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query
            ->where('product_id', $productId)
            ->where('product_variant_key', $productVariantId ?? 0)
            ->where('warehouse_id', $warehouseId);
    }
}