<?php

namespace App\Modules\PurchaseOrder\Repositories\Contracts;

interface PurchaseOrderRepositoryInterface
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
