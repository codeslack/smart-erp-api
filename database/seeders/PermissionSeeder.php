<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

use App\Core\Modules\ModuleRegistry;

use App\Modules\Rbac\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (
            array_unique(
                ModuleRegistry::permissions()
            )
            as $permission
        ) {

            Permission::firstOrCreate(
                [
                    'name' => $permission,
                    'guard_name' => 'sanctum',
                ]
            );
        }
    }
}
