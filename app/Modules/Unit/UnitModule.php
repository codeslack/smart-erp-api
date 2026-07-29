<?php

namespace App\Modules\Unit;

use App\Core\Modules\Contracts\ModuleInterface;

use App\Modules\Unit\Providers\UnitServiceProvider;
use App\Modules\Unit\Services\UnitSetupService;

class UnitModule implements ModuleInterface
{
    public static function name(): string
    {
        return 'Unit';
    }

    public static function permissions(): array
    {
        return [

            'unit.view',
            'unit.create',
            'unit.update',
            'unit.delete',
        ];
    }

    public static function serviceProviders(): array
    {
        return [

            UnitServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [

            UnitSetupService::class,
        ];
    }
}