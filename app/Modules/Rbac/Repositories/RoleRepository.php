<?php

namespace App\Modules\Rbac\Repositories;

use InvalidArgumentException;

use App\Core\Repositories\BaseRepository;

use App\Modules\Rbac\Models\Role;

use App\Modules\Rbac\Repositories\Contracts\RoleRepositoryInterface;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RoleRepository
    extends BaseRepository
    implements RoleRepositoryInterface
{
    public function __construct(Role $model)
    {
        parent::__construct($model);
    }

    /**
     * Get all roles belonging to the active tenant.
     */
    public function all(): Collection
    {
        return $this->tenantQuery()->get();
    }

    /**
     * Paginate tenant roles.
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->tenantQuery()
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Paginate roles with permissions.
     */
    public function paginateWithPermissions(
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->tenantQuery()
            ->with('permissions')
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Find role by internal database ID.
     */
    public function findById(int $id): ?Role
    {
        return $this->tenantQuery()
            ->whereKey($id)
            ->first();
    }

    /**
     * Find role by UUID.
     */
    public function findByUuid(string $uuid): ?Role
    {
        return $this->tenantQuery()
            ->where('uuid', $uuid)
            ->first();
    }

    public function findByName(
        string $name,
        string $guardName = 'sanctum'
    ): ?Role {
        return $this->tenantQuery()
            ->where('name', $name)
            ->where('guard_name', $guardName)
            ->first();
    }

    /**
     * Find role by UUID or fail.
     */
    public function findOrFail(string $uuid): Role
    {
        return $this->tenantQuery()
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    /**
     * Find role with permissions.
     */
    public function findByUuidWithPermissions(
        string $uuid
    ): ?Role {
        return $this->tenantQuery()
            ->with('permissions')
            ->where('uuid', $uuid)
            ->first();
    }

    /**
     * Check UUID existence within active tenant.
     */
    public function existsByUuid(string $uuid): bool
    {
        return $this->tenantQuery()
            ->where('uuid', $uuid)
            ->exists();
    }

    /**
     * Count active tenant roles.
     */
    public function count(): int
    {
        return $this->tenantQuery()->count();
    }

    /**
     * Create a role.
     *
     * Role model also validates tenant ownership.
     */
    public function create(array $data): Role
    {
        return $this->tenantQuery()->create($data);
    }

    /**
     * Check role name uniqueness within tenant and guard.
     */
    public function existsByName(
        string $name,
        string $guardName = 'sanctum',
        ?int $ignoreId = null
    ): bool {
        return $this->tenantQuery()
            ->where('name', $name)
            ->where('guard_name', $guardName)
            ->when(
                $ignoreId,
                fn(Builder $query) => $query->where(
                    $query->getModel()->getQualifiedKeyName(),
                    '!=',
                    $ignoreId
                )
            )
            ->exists();
    }

    /**
     * Update only a role belonging to the active tenant.
     */
    public function update(
        Model $model,
        array $data
    ): Model {
        if (! $model instanceof Role) {
            throw new InvalidArgumentException(
                'Expected an instance of Role.'
            );
        }

        $role = $this->tenantQuery()
            ->whereKey($model->getKey())
            ->firstOrFail();

        // Tenant ownership cannot be changed after creation.
        unset($data['tenant_id']);

        $role->update($data);

        return $role->refresh();
    }

    /**
     * Delete only a role belonging to the active tenant.
     */
    public function delete(Model $model): bool
    {
        if (! $model instanceof Role) {
            throw new InvalidArgumentException(
                'Expected an instance of Role.'
            );
        }

        $role = $this->tenantQuery()
            ->whereKey($model->getKey())
            ->firstOrFail();

        return (bool) $role->delete();
    }

    /**
     * Always restrict role queries to the active tenant.
     */
    private function tenantQuery(): Builder
    {
        $query = $this->model->newQuery();

        $tenantId = tenant()?->id;

        if (! $tenantId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('tenant_id', $tenantId);
    }
}
