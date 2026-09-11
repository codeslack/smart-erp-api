<?php

namespace App\Modules\Inventory\Repositories;

use Illuminate\Database\Eloquent\Collection;

use App\Modules\Inventory\Enums\InventoryCostLayerStatusEnum;

use App\Modules\Inventory\Models\InventoryCostLayer;
use App\Modules\Inventory\Repositories\Contracts\InventoryCostLayerRepositoryInterface;

class InventoryCostLayerRepository
    implements InventoryCostLayerRepositoryInterface
{
    public function __construct(
        protected InventoryCostLayer $model
    ) {}

    public function create(
        array $data
    ): InventoryCostLayer {

        return $this->model
            ->newQuery()
            ->create($data);
    }

    public function availableLayers(
        int $productId,
        ?int $productVariantId,
        int $warehouseId
    ): Collection {

        return $this->model
            ->newQuery()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)

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
                'status',
                InventoryCostLayerStatusEnum::OPEN->value
            )

            ->where(
                'remaining_quantity',
                '>',
                0
            )

            ->orderBy(
                'layer_date'
            )

            ->orderBy(
                'id'
            )

            ->lockForUpdate()

            ->get();
    }

    public function findById(
        int $id
    ): ?InventoryCostLayer {

        return $this->model
            ->newQuery()
            ->where('tenant_id', tenantId())
            ->find($id);
    }

    public function findByStockLedgerId(
        int $stockLedgerId
    ): ?InventoryCostLayer {

        return $this->model
            ->newQuery()
            ->where(
                'stock_ledger_id',
                $stockLedgerId
            )
            ->lockForUpdate()
            ->first();
    }    

    public function update(
        InventoryCostLayer $layer,
        array $data
    ): InventoryCostLayer {

        $layer->update($data);

        return $layer->refresh();
    }

    public function delete(
        InventoryCostLayer $layer
    ): bool {

        return (bool) $layer->delete();
    }
}