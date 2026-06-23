<?php

namespace App\Modules\CustomerReceipt\Repositories;

use App\Modules\CustomerReceipt\Models\CustomerReceipt;
use App\Modules\CustomerReceipt\Repositories\Contracts\CustomerReceiptRepositoryInterface;

class CustomerReceiptRepository implements CustomerReceiptRepositoryInterface
{
    public function paginate()
    {
        return CustomerReceipt::query()
            ->with([
                'customer',
                'allocations.sale'
            ])
            ->latest()
            ->paginate();
    }

    public function find(
        int $id
    ) {
        return CustomerReceipt::query()
            ->with([
                'customer',
                'allocations.sale'
            ])
            ->findOrFail(
                $id
            );
    }

    public function create(
        array $data
    ) {
        return CustomerReceipt::create(
            $data
        );
    }

    public function update(
        int $id,
        array $data
    ) {
        $receipt = $this->find(
            $id
        );

        $receipt->update(
            $data
        );

        return $receipt->fresh();
    }

    public function delete(
        int $id
    ) {
        return CustomerReceipt::destroy(
            $id
        );
    }
}