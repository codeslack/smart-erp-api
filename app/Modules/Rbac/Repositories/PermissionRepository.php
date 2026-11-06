<?php

namespace App\Modules\Rbac\Repositories;

use InvalidArgumentException;

use App\Core\Repositories\BaseRepository;

use App\Modules\Rbac\Models\Permission;

use App\Modules\Rbac\Repositories\Contracts\PermissionRepositoryInterface;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PermissionRepository
    extends BaseRepository
    implements PermissionRepositoryInterface
{
    public function __construct(Permission $model)
    {
        parent::__construct($model);
    }

    /**
     * Get all permissions for the Sanctum guard.
     */
    public function all(): Collection
    {
        return $this->guardQuery()->get();
    }

    /**
     * Paginate Sanctum permissions.
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->guardQuery()
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Paginate permissions by guard.
     */
    public function paginateByGuard(
        string $guardName = 'sanctum',
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->guardQuery($guardName)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Find permission by internal ID.
     */
    public function findById(int $id): ?Permission
    {
        return $this->guardQuery()
            ->whereKey($id)
            ->first();
    }

    /**
     * Find permission by UUID.
     */
    public function findByUuid(string $uuid): ?Permission
    {
        return $this->guardQuery()
            ->where('uuid', $uuid)
            ->first();
    }

    /**
     * Find permission by UUID or fail.
     */
    public function findOrFail(string $uuid): Permission
    {
        return $this->guardQuery()
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    /**
     * Find permission by UUID and guard.
     */
    public function findByUuidAndGuard(
        string $uuid,
        string $guardName = 'sanctum'
    ): ?Permission {
        return $this->guardQuery($guardName)
            ->where('uuid', $uuid)
            ->first();
    }

    /**
     * Find multiple permissions by UUID and guard.
     */
    public function findByUuidsAndGuard(
        array $uuids,
        string $guardName = 'sanctum'
    ): Collection {
        return $this->guardQuery($guardName)
            ->whereIn('uuid', $uuids)
            ->get();
    }

    /**
     * Check UUID existence.
     */
    public function existsByUuid(string $uuid): bool
    {
        return $this->guardQuery()
            ->where('uuid', $uuid)
            ->exists();
    }

    /**
     * Count Sanctum permissions.
     */
    public function count(): int
    {
        return $this->guardQuery()->count();
    }

    /**
     * Create a permission for the Sanctum guard.
     */
    public function create(array $data): Permission
    {
        $data['guard_name'] = 'sanctum';

        return $this->guardQuery()->create($data);
    }

    /**
     * Update a permission without changing its guard.
     */
    public function update(
        Model $model,
        array $data
    ): Model {
        if (! $model instanceof Permission) {
            throw new InvalidArgumentException(
                'Expected an instance of Permission.'
            );
        }

        $permission = $this->guardQuery()
            ->whereKey($model->getKey())
            ->firstOrFail();

        unset($data['guard_name']);

        $permission->update($data);

        return $permission->refresh();
    }

    /**
     * Delete a Sanctum permission.
     */
    public function delete(Model $model): bool
    {
        if (! $model instanceof Permission) {
            throw new InvalidArgumentException(
                'Expected an instance of Permission.'
            );
        }

        $permission = $this->guardQuery()
            ->whereKey($model->getKey())
            ->firstOrFail();

        return (bool) $permission->delete();
    }

    /**
     * Build a global permission query for the requested guard.
     */
    private function guardQuery(
        string $guardName = 'sanctum'
    ): Builder {
        return $this->model
            ->newQuery()
            ->where('guard_name', $guardName);
    }
}
