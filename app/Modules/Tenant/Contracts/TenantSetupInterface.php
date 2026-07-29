<?php

namespace App\Modules\Tenant\Contracts;

use App\Modules\Tenant\Models\Tenant;

interface TenantSetupInterface
{
    public function setup(
        Tenant $tenant
    ): void;
}