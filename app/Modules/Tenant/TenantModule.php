<?php

namespace App\Modules\Tenant;

use App\Core\Modules\Contracts\ModuleInterface;
use App\Modules\Tenant\Providers\TenantServiceProvider;

class TenantModule implements ModuleInterface
{
    public static function name(): string
    {
        return 'Tenant';
    }

    public static function permissions(): array
    {
        return [
            'tenant.view',
            'tenant.create',
            'tenant.update',
            'tenant.delete',
        ];
    }

    public static function serviceProviders(): array
    {
        return [
            TenantServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [];
    }
}