<?php

namespace App\Modules\SalesReturn\Repositories;

use App\Modules\SalesReturn\Models\SalesReturn;
use App\Modules\SalesReturn\Repositories\Contracts\SalesReturnRepositoryInterface;

class SalesReturnRepository
    implements SalesReturnRepositoryInterface
{
    public function paginate()
    {
        return SalesReturn::query()
            ->with('items')
            ->latest('id')
            ->paginate();
    }

    public function find(int $id)
    {
        return SalesReturn::query()
            ->with('items')
            ->findOrFail($id);
    }

    public function create(array $data)
    {
        return SalesReturn::create(
            $data
        );
    }

    public function update(
        int $id,
        array $data
    ) {
        $salesReturn = $this->find(
            $id
        );

        $salesReturn->update(
            $data
        );

        return $salesReturn->fresh();
    }

    public function delete(int $id)
    {
        return $this->find(
            $id
        )->delete();
    }
}
