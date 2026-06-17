<?php

namespace App\Modules\PurchaseReturn\Repositories;

use App\Modules\PurchaseReturn\Models\PurchaseReturn;
use App\Modules\PurchaseReturn\Repositories\Contracts\PurchaseReturnRepositoryInterface;

class PurchaseReturnRepository implements PurchaseReturnRepositoryInterface
{
    public function paginate()
    {
        return PurchaseReturn::query()
            ->with([
                'supplier',
                'purchase',
            ])
            ->latest()
            ->paginate();
    }

    public function find(int $id)
    {
        return PurchaseReturn::query()
            ->with([
                'supplier',
                'purchase',
                'items',
            ])
            ->findOrFail($id);
    }

    public function create(array $data)
    {
        return PurchaseReturn::create(
            $data
        );
    }

    public function update(
        int $id,
        array $data
    ) {
        $purchaseReturn = $this->find(
            $id
        );

        $purchaseReturn->update(
            $data
        );

        return $purchaseReturn->fresh();
    }

    public function delete(int $id)
    {
        return PurchaseReturn::destroy(
            $id
        );
    }
}
