<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Accounting\Services\AccountingSetupService;

class AccountingSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::first();

        if ($tenant) {
            // Resolve service from the container and run setup
            app(AccountingSetupService::class)->setup($tenant);
        }
    }
}
