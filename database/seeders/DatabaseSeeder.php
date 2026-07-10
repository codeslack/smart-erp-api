<?php

namespace Database\Seeders;

use App\Modules\Rbac\Seeders\PermissionSeeder;
use App\Modules\Rbac\Seeders\RoleSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            TenantSeeder::class,
            AccountingSeeder::class,
            AdminUserSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);
    }
}
