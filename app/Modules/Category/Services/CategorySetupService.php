<?php

namespace App\Modules\Category\Services;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Contracts\TenantSetupInterface;

class CategorySetupService
implements TenantSetupInterface
{
    public function setup(
        Tenant $tenant
    ): void {
        //
    }
}
