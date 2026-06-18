<?php

namespace App\Modules\StockTransfer\Repositories;

use App\Modules\StockTransfer\Models\StockTransfer;
use App\Modules\StockTransfer\Repositories\Contracts\StockTransferRepositoryInterface;

class StockTransferRepository
    implements StockTransferRepositoryInterface
{
    public function paginate()
    {
        return StockTransfer::query()
            ->with([
                'items',
                'fromWarehouse',
                'toWarehouse',
            ])
            ->latest('id')
            ->paginate();
    }

    public function find(int $id)
    {
        return StockTransfer::query()
            ->with([
                'items',
                'fromWarehouse',
                'toWarehouse',
            ])
            ->findOrFail($id);
    }

    public function create(array $data)
    {
        return StockTransfer::create(
            $data
        );
    }

    public function update(
        int $id,
        array $data
    ) {
        $transfer = $this->find(
            $id
        );

        $transfer->update(
            $data
        );

        return $transfer->fresh();
    }

    public function delete(int $id)
    {
        return $this->find(
            $id
        )->delete();
    }
}
