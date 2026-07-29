<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

use App\Modules\Rbac\Models\Permission;
use App\Modules\Rbac\Enums\PermissionEnum;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (
            PermissionEnum::cases()
            as $permission
        ) {

            Permission::firstOrCreate(
                [
                    'name' =>
                        $permission->value,

                    'guard_name' =>
                        'sanctum',
                ]
            );
        }
    }
}
