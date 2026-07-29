<?php

namespace App\Core\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;

/**
 * @template TModel of Model
 */
abstract class BaseRepository
    implements BaseRepositoryInterface
{
    /**
     * @param TModel $model
     */
    public function __construct(
        protected Model $model
    ) {}

    public function all(): Collection
    {
        return $this->model
            ->newQuery()
            ->get();
    }

    public function paginate(
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->model
            ->newQuery()
            ->latest()
            ->paginate($perPage);
    }

    public function findById(
        int $id
    ): ?Model {
        return $this->model
            ->newQuery()
            ->find($id);
    }

    public function findByUuid(
        string $uuid
    ): ?Model {
        return $this->model
            ->newQuery()
            ->where('uuid', $uuid)
            ->first();
    }

    public function findOrFail(
        string $uuid
    ): Model {
        return $this->model
            ->newQuery()
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    public function existsByUuid(
        string $uuid
    ): bool {
        return $this->model
            ->newQuery()
            ->where('uuid', $uuid)
            ->exists();
    }

    public function count(): int
    {
        return $this->model
            ->newQuery()
            ->count();
    }

    public function create(
        array $data
    ): Model {
        return $this->model
            ->newQuery()
            ->create($data);
    }

    public function update(
        Model $model,
        array $data
    ): Model {

        $model->update($data);

        return $model->refresh();
    }

    public function delete(
        Model $model
    ): bool {
        return (bool) $model->delete();
    }
}