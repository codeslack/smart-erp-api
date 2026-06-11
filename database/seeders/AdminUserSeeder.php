<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\User\Models\User;
use App\Modules\Tenant\Models\Tenant;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::first();

        User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
    }
}
