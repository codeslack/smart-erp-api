<?php

namespace App\Modules\Rbac\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

use App\Core\Modules\ModuleRegistry;
use App\Modules\Rbac\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);

        $registrar->forgetCachedPermissions();

        foreach (ModuleRegistry::permissions() as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'sanctum',
            ]);
        }

        $registrar->forgetCachedPermissions();
    }
}