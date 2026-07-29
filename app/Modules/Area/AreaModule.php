<?php

namespace App\Modules\Area;

use App\Core\Modules\Contracts\ModuleInterface;

use App\Modules\Area\Providers\AreaServiceProvider;
use App\Modules\Area\Services\AreaSetupService;

class AreaModule
    implements ModuleInterface
{
    public static function name(): string
    {
        return 'Area';
    }

    public static function permissions(): array
    {
        return [

            'area.view',
            'area.create',
            'area.update',
            'area.delete',
        ];
    }

    public static function serviceProviders(): array
    {
        return [

            AreaServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [

            AreaSetupService::class,
        ];
    }
}