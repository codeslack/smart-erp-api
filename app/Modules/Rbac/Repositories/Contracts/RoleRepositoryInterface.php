<?php

namespace App\Modules\Rbac\Repositories\Contracts;

use App\Modules\Rbac\Models\Role;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;

interface RoleRepositoryInterface
    extends BaseRepositoryInterface
{
    public function paginateWithPermissions(
        int $perPage = 15
    ): LengthAwarePaginator;

    public function findByUuidWithPermissions(
        string $uuid
    ): ?Role;

    public function existsByName(
        string $name,
        string $guardName = 'sanctum',
        ?int $ignoreId = null
    ): bool;

    public function findByName(
        string $name,
        string $guardName = 'sanctum'
    ): ?Role;
}
