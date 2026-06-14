<?php

namespace App\Core\Contracts;

interface BaseRepositoryInterface
{
    public function all();

    public function query();

    public function paginate(int $perPage = 15);

    public function find(int|string $id);

    public function create(array $data);

    public function update(int|string $id, array $data);

    public function delete(int|string $id);
}