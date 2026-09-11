<?php

namespace App\Modules\Inventory\Repositories;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

use App\Modules\Inventory\Models\InventoryCostLayerConsumption;
use App\Modules\Inventory\Repositories\Contracts\InventoryCostLayerConsumptionRepositoryInterface;

class InventoryCostLayerConsumptionRepository
    implements InventoryCostLayerConsumptionRepositoryInterface
{
    public function __construct(
        protected InventoryCostLayerConsumption $model
    ) {}

    public function create(
        array $data
    ): InventoryCostLayerConsumption {
        return $this->model
            ->newQuery()
            ->create($data);
    }

    public function createMany(
        int $stockLedgerId,
        Collection $consumptions
    ): EloquentCollection {
        $created = new EloquentCollection();

        foreach ($consumptions as $consumption) {
            $created->push(
                $this->create([
                    'inventory_cost_layer_id' =>
                        $consumption['layer_id'],

                    'stock_ledger_id' =>
                        $stockLedgerId,

                    'quantity' =>
                        $consumption['quantity'],

                    'unit_cost' =>
                        $consumption['unit_cost'],

                    'total_cost' =>
                        $consumption['total_cost'],

                    'created_by' =>
                        userId(),
                ])
            );
        }

        return $created;
    }

    public function findByStockLedgerId(
        int $stockLedgerId
    ): EloquentCollection {
        return $this->model
            ->newQuery()
            ->where('stock_ledger_id', $stockLedgerId)
            ->orderBy('id')
            ->get();
    }

    public function findForUpdateByStockLedgerId(
        int $stockLedgerId
    ): EloquentCollection {

        return $this->model
            ->newQuery()
            ->where(
                'stock_ledger_id',
                $stockLedgerId
            )
            ->lockForUpdate()
            ->get();
    }    

    public function existsForStockLedger(
        int $stockLedgerId
    ): bool {
        return $this->model
            ->newQuery()
            ->where('stock_ledger_id', $stockLedgerId)
            ->exists();
    }

    public function totalCostByStockLedger(
        int $stockLedgerId
    ): float {
        return (float) $this->model
            ->newQuery()
            ->where('stock_ledger_id', $stockLedgerId)
            ->sum('total_cost');
    }

    public function deleteByStockLedgerId(
        int $stockLedgerId
    ): int {

        return $this->model
            ->newQuery()
            ->where(
                'stock_ledger_id',
                $stockLedgerId
            )
            ->delete();
    }
}