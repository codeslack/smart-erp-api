<?php

use App\Core\Tenant\TenantManager;

if (!function_exists('tenant')) {

    function tenant()
    {
        return app(TenantManager::class)
            ->getTenant();
    }
}