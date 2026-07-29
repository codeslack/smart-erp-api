<?php

namespace App\Core\Repositories\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BaseRepositoryInterface
{
    public function all(): Collection;

    public function paginate(
        int $perPage = 15
    ): LengthAwarePaginator;

    public function findById(
        int $id
    ): ?Model;

    public function findByUuid(
        string $uuid
    ): ?Model;

    public function findOrFail(
        string $uuid
    ): Model;

    public function existsByUuid(
        string $uuid
    ): bool;

    public function count(): int;

    public function create(
        array $data
    ): Model;

    public function update(
        Model $model,
        array $data
    ): Model;

    public function delete(
        Model $model
    ): bool;
}