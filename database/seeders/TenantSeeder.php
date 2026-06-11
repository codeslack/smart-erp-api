<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Tenant\Models\Tenant;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Tenant::create([
            'name' => 'Demo Company',
            'slug' => 'demo-company',
            'domain' => 'demo.local',
        ]);
    }
}
