<?php

namespace App\Modules\Area\Services;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Contracts\TenantSetupInterface;

class AreaSetupService
    implements TenantSetupInterface
{
    public function setup(
        Tenant $tenant
    ): void {
        //
    }
}