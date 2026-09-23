<?php

namespace App\Modules\Rbac;

use App\Core\Modules\Contracts\ModuleInterface;
use App\Modules\Rbac\Providers\RbacServiceProvider;

class RbacModule implements ModuleInterface
{
    public static function name(): string
    {
        return 'RBAC';
    }

    public static function permissions(): array
    {
        return [];
    }

    public static function serviceProviders(): array
    {
        return [
            RbacServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [];
    }
}