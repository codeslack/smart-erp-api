<?php

namespace App\Modules\Brand\Services;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Contracts\TenantSetupInterface;

class BrandSetupService
    implements TenantSetupInterface
{
    public function setup(
        Tenant $tenant
    ): void {
        //
    }
}