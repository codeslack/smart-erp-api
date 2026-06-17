<?php

namespace App\Modules\PurchaseReturn\Repositories\Contracts;

interface PurchaseReturnRepositoryInterface
{
    public function paginate();

    public function find(int $id);

    public function create(array $data);

    public function update(
        int $id,
        array $data
    );

    public function delete(int $id);
}
