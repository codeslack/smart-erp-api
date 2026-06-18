<?php

namespace App\Modules\SalesReturn\Repositories\Contracts;

interface SalesReturnRepositoryInterface
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
