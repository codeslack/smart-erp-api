<?php

namespace App\Modules\Warehouse;

use App\Core\Modules\Contracts\ModuleInterface;

use App\Modules\Warehouse\Providers\WarehouseServiceProvider;
use App\Modules\Warehouse\Services\WarehouseSetupService;

class WarehouseModule implements ModuleInterface
{
    public static function name(): string
    {
        return 'Warehouse';
    }

    public static function permissions(): array
    {
        return [

            'warehouse.view',

            'warehouse.create',

            'warehouse.update',

            'warehouse.delete',
        ];
    }

    public static function serviceProviders(): array
    {
        return [

            WarehouseServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [

            WarehouseSetupService::class,

        ];
    }
}