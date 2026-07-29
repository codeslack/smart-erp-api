<?php

namespace App\Modules\Rbac\Services;

use App\Modules\Tenant\Models\Tenant;

use App\Modules\Rbac\Models\Role;
use App\Modules\Rbac\Models\Permission;

use App\Modules\Rbac\Enums\RoleEnum;

use App\Modules\Tenant\Contracts\TenantSetupInterface;

class PermissionSetupService
    implements TenantSetupInterface
{
    public function setup(
        Tenant $tenant
    ): void {

        $this->setupAdministrator(
            $tenant
        );

        $this->setupViewer(
            $tenant
        );
    }

    protected function setupAdministrator(
        Tenant $tenant
    ): void {

        $role =
            $this->findRole(
                $tenant,
                RoleEnum::ADMINISTRATOR
            );

        if (! $role) {
            return;
        }

        $role->syncPermissions(
            Permission::query()
                ->pluck('name')
                ->toArray()
        );
    }

    protected function setupViewer(
        Tenant $tenant
    ): void {

        $role =
            $this->findRole(
                $tenant,
                RoleEnum::VIEWER
            );

        if (! $role) {
            return;
        }

        $permissions =
            Permission::query()

                ->where(
                    'name',
                    'like',
                    '%.view'
                )

                ->pluck('name')
                ->toArray();

        $role->syncPermissions(
            $permissions
        );
    }

    protected function findRole(
        Tenant $tenant,
        RoleEnum $role
    ): ?Role {

        return Role::query()

            ->where(
                'tenant_id',
                $tenant->id
            )

            ->where(
                'name',
                $role->value
            )

            ->first();
    }
}
