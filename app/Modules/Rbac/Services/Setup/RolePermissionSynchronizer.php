<?php

namespace App\Modules\Rbac\Services\Setup;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Rbac\Models\Role;
use App\Modules\Rbac\Enums\RoleEnum;

class RolePermissionSynchronizer
{
    private const GUARD_NAME = 'sanctum';

    public function sync(
        Tenant $tenant,
        RoleEnum $roleEnum,
        array $permissions,
        array $availablePermissions
    ): void {
        $role = Role::query()
            ->where('tenant_id', $tenant->id)
            ->where('name', $roleEnum->value)
            ->where('guard_name', self::GUARD_NAME)
            ->first();

        if (! $role) {
            return;
        }

        $permissions = array_values(
            array_intersect(
                $permissions,
                $availablePermissions
            )
        );

        $role->syncPermissions($permissions);
    }
}