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
        return [
            'role.view',
            'role.create',
            'role.update',
            'role.delete',
            'role.assign',

            'permission.view',
            'permission.assign',
        ];
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