<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

use App\Core\Modules\ModuleRegistry;
use App\Modules\Rbac\Models\Permission;

use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);

        $registrar->forgetCachedPermissions();

        try {
            foreach (ModuleRegistry::permissions() as $permission) {
                Permission::firstOrCreate([
                    'name' => $permission,
                    'guard_name' => 'sanctum',
                ]);
            }
        } finally {
            $registrar->forgetCachedPermissions();
        }
    }
}