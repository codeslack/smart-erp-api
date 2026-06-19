<?php

namespace App\Modules\PurchaseOrder\Repositories;

use App\Modules\PurchaseOrder\Models\PurchaseOrder;
use App\Modules\PurchaseOrder\Repositories\Contracts\PurchaseOrderRepositoryInterface;

class PurchaseOrderRepository
    implements PurchaseOrderRepositoryInterface
{
    public function paginate()
    {
        return PurchaseOrder::query()
            ->with([
                'supplier',
                'items.product',
                'items.warehouse',
            ])
            ->latest('id')
            ->paginate();
    }

    public function find(int $id)
    {
        return PurchaseOrder::query()
            ->with([
                'supplier',
                'items.product',
                'items.warehouse',
            ])
            ->findOrFail($id);
    }

    public function create(array $data)
    {
        return PurchaseOrder::create(
            $data
        );
    }

    public function update(
        int $id,
        array $data
    ) {
        $purchaseOrder = $this->find(
            $id
        );

        $purchaseOrder->update(
            $data
        );

        return $purchaseOrder->fresh();
    }

    public function delete(int $id)
    {
        return $this->find(
            $id
        )->delete();
    }
}
