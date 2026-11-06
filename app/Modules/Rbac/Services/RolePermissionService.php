<?php

namespace App\Modules\Rbac\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use App\Core\Services\BaseService;
use App\Core\Exceptions\BusinessException;

use App\Modules\Rbac\Models\Role;

use App\Modules\Rbac\Repositories\Contracts\RoleRepositoryInterface;
use App\Modules\Rbac\Repositories\Contracts\PermissionRepositoryInterface;

class RolePermissionService extends BaseService
{
    private const GUARD_NAME = 'sanctum';

    public function __construct(
        protected RoleRepositoryInterface $roles,
        protected PermissionRepositoryInterface $permissions
    ) {}

    public function paginatePermissions(
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->permissions->paginateByGuard(
            self::GUARD_NAME,
            $perPage
        );
    }

    public function findRolePermissions(
        string $roleUuid
    ): Role {
        return $this->roles
            ->findByUuidWithPermissions($roleUuid)
            ?? throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
    }

    public function syncPermissions(
        string $roleUuid,
        array $permissionUuids
    ): Role {
        $role = $this->roles->findOrFail($roleUuid);

        $permissionUuids = array_values(
            array_unique($permissionUuids)
        );

        return DB::transaction(function () use (
            $role,
            $permissionUuids
        ) {
            $permissions = $this->permissions
                ->findByUuidsAndGuard(
                    $permissionUuids,
                    self::GUARD_NAME
                );

            if ($permissions->count() !== count($permissionUuids)) {
                throw new BusinessException(
                    'One or more selected permissions are invalid.'
                );
            }

            $role->syncPermissions($permissions);

            return $role->load('permissions');
        });
    }
}