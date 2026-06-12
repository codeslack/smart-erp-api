<?php

namespace App\Modules\Rbac\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Rbac\Models\Role;
use App\Modules\Rbac\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {

            $adminRole = Role::firstOrCreate([
                'tenant_id'  => $tenant->id,
                'name'       => 'Administrator',
                'guard_name' => 'sanctum',
            ]);

            $managerRole = Role::firstOrCreate([
                'tenant_id'  => $tenant->id,
                'name'       => 'Manager',
                'guard_name' => 'sanctum',
            ]);

            $accountantRole = Role::firstOrCreate([
                'tenant_id'  => $tenant->id,
                'name'       => 'Accountant',
                'guard_name' => 'sanctum',
            ]);

            $salesRole = Role::firstOrCreate([
                'tenant_id'  => $tenant->id,
                'name'       => 'Sales Executive',
                'guard_name' => 'sanctum',
            ]);

            $purchaseRole = Role::firstOrCreate([
                'tenant_id'  => $tenant->id,
                'name'       => 'Purchase Executive',
                'guard_name' => 'sanctum',
            ]);

            $warehouseRole = Role::firstOrCreate([
                'tenant_id'  => $tenant->id,
                'name'       => 'Warehouse Manager',
                'guard_name' => 'sanctum',
            ]);

            $viewerRole = Role::firstOrCreate([
                'tenant_id'  => $tenant->id,
                'name'       => 'Viewer',
                'guard_name' => 'sanctum',
            ]);

            // Administrator gets everything
            $adminRole->syncPermissions(
                Permission::pluck('name')->toArray()
            );
        }
    }
}
