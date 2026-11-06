<?php

namespace App\Modules\Rbac\Services;

use Spatie\Permission\PermissionRegistrar;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Contracts\TenantSetupInterface;

use App\Modules\Rbac\Models\Permission;
use App\Modules\Rbac\Enums\RoleEnum;

use App\Modules\Rbac\Services\Setup\DefaultRolePermissionMap;
use App\Modules\Rbac\Services\Setup\RolePermissionSynchronizer;

class PermissionSetupService implements TenantSetupInterface
{
    private const GUARD_NAME = 'sanctum';

    public function __construct(
        protected PermissionRegistrar $registrar,
        protected DefaultRolePermissionMap $permissionMap,
        protected RolePermissionSynchronizer $synchronizer
    ) {}

    public function setup(Tenant $tenant): void
    {
        $this->registrar->setPermissionsTeamId($tenant->id);

        try {
            $availablePermissions = Permission::query()
                ->where('guard_name', self::GUARD_NAME)
                ->pluck('name')
                ->all();

            $this->synchronizer->sync(
                $tenant,
                RoleEnum::ADMINISTRATOR,
                $availablePermissions,
                $availablePermissions
            );

            foreach ($this->permissionMap->all() as $role => $permissions) {
                $this->synchronizer->sync(
                    $tenant,
                    RoleEnum::from($role),
                    $permissions,
                    $availablePermissions
                );
            }
        } finally {
            $this->registrar->setPermissionsTeamId(
                null
            );
        }
    }
}