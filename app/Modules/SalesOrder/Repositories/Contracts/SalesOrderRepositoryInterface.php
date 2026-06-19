<?php

namespace App\Modules\SalesOrder\Repositories\Contracts;

interface SalesOrderRepositoryInterface
{
    public function paginate(
        int $perPage = 15
    );

    public function find(
        int $id
    );

    public function create(
        array $data
    );

    public function update(
        int $id,
        array $data
    );

    public function delete(
        int $id
    );
}
