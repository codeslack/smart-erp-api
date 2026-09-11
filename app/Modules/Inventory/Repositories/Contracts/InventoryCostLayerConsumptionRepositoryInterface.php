<?php

namespace App\Modules\Inventory\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

use App\Modules\Inventory\Models\InventoryCostLayerConsumption;

interface InventoryCostLayerConsumptionRepositoryInterface
{
    public function create(array $data): InventoryCostLayerConsumption;

    public function createMany(
        int $stockLedgerId,
        Collection $consumptions
    ): EloquentCollection;

    public function findByStockLedgerId(
        int $stockLedgerId
    ): EloquentCollection;

    public function existsForStockLedger(
        int $stockLedgerId
    ): bool;

    public function totalCostByStockLedger(
        int $stockLedgerId
    ): float;

    public function deleteByStockLedgerId(
        int $stockLedgerId
    ): int;

    public function findForUpdateByStockLedgerId(
        int $stockLedgerId
    ): EloquentCollection;
}