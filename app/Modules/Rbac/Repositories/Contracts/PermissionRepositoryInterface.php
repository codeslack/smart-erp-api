<?php

namespace App\Modules\Rbac\Repositories\Contracts;

use App\Modules\Rbac\Models\Permission;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;

interface PermissionRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateByGuard(
        string $guardName = 'sanctum',
        int $perPage = 15
    ): LengthAwarePaginator;

    public function findByUuidAndGuard(
        string $uuid,
        string $guardName = 'sanctum'
    ): ?Permission;

    public function findByUuidsAndGuard(
        array $uuids,
        string $guardName = 'sanctum'
    ): Collection;
}
