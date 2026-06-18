<?php

namespace App\Modules\StockAdjustment\Repositories;

use App\Modules\StockAdjustment\Models\StockAdjustment;
use App\Modules\StockAdjustment\Repositories\Contracts\StockAdjustmentRepositoryInterface;

class StockAdjustmentRepository
    implements StockAdjustmentRepositoryInterface
{
    public function paginate()
    {
        return StockAdjustment::query()
            ->with('items')
            ->latest('id')
            ->paginate();
    }

    public function find(int $id)
    {
        return StockAdjustment::query()
            ->with('items')
            ->findOrFail($id);
    }

    public function create(array $data)
    {
        return StockAdjustment::create(
            $data
        );
    }

    public function update(
        int $id,
        array $data
    ) {
        $adjustment = $this->find(
            $id
        );

        $adjustment->update(
            $data
        );

        return $adjustment->fresh();
    }

    public function delete(int $id)
    {
        return $this->find(
            $id
        )->delete();
    }
}
