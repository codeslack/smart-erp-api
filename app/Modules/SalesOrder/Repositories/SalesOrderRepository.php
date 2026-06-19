<?php

namespace App\Modules\SalesOrder\Repositories;

use App\Modules\SalesOrder\Models\SalesOrder;
use App\Modules\SalesOrder\Repositories\Contracts\SalesOrderRepositoryInterface;

class SalesOrderRepository
    implements SalesOrderRepositoryInterface
{
    public function paginate(
        int $perPage = 15
    ) {
        return SalesOrder::with(
            'items'
        )->latest()->paginate(
            $perPage
        );
    }

    public function find(
        int $id
    ) {
        return SalesOrder::with(
            'items'
        )->findOrFail(
            $id
        );
    }

    public function create(
        array $data
    ) {
        return SalesOrder::create(
            $data
        );
    }

    public function update(
        int $id,
        array $data
    ) {
        $salesOrder = $this->find(
            $id
        );

        $salesOrder->update(
            $data
        );

        return $salesOrder->fresh(
            'items'
        );
    }

    public function delete(
        int $id
    ) {
        return SalesOrder::destroy(
            $id
        );
    }
}
