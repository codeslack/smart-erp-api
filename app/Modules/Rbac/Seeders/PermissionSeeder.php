<?php

namespace App\Modules\Rbac\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Rbac\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $actions = [
            'view',
            'create',
            'update',
            'delete',
            'approve',
            'export',
            'print',
        ];

        $modules = config('erp_permissions', []);

        foreach ($modules as $module) {

            foreach ($actions as $action) {

                Permission::firstOrCreate([
                    'name'       => "{$module}.{$action}",
                    'guard_name' => 'sanctum',
                ]);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
