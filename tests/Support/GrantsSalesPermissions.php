<?php

namespace Tests\Support;

use App\Core\Tenant\TenantManager;
use App\Modules\Rbac\Models\Permission;
use App\Modules\Rbac\Models\Role;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Spatie\Permission\PermissionRegistrar;

trait GrantsSalesPermissions
{
    protected function grantSalesPermissions(
        User $user,
        Tenant $tenant
    ): void {
        $registrar = app(PermissionRegistrar::class);

        app(TenantManager::class)->setTenant($tenant);
        $registrar->setPermissionsTeamId($tenant->id);

        try {
            $role = Role::firstOrCreate([
                'tenant_id' => $tenant->id,
                'name' => 'Sales Feature Test Role',
                'guard_name' => 'sanctum',
            ]);

            $permissions = [
                'sale.view',
                'sale.create',
                'sale.update',
                'sale.delete',
                'sale.confirm',
                'sale.cancel',
                'sales-return.view',
                'sales-return.create',
                'sales-return.update',
                'sales-return.delete',
                'sales-return.approve',
                'sales-return.confirm',
                'sales-return.cancel',
            ];

            $permissionModels = array_map(
                fn (string $name) => Permission::firstOrCreate([
                    'name' => $name,
                    'guard_name' => 'sanctum',
                ]),
                $permissions
            );

            $role->syncPermissions($permissionModels);
            $user->syncRoles([$role]);
        } finally {
            $registrar->setPermissionsTeamId(null);
            $registrar->forgetCachedPermissions();
        }
    }
}
