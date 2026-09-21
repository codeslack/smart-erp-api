<?php

namespace App\Modules\Inventory;

use App\Core\Modules\Contracts\ModuleInterface;

use App\Modules\Inventory\Providers\InventoryServiceProvider;

class InventoryModule implements ModuleInterface
{
    public static function name(): string
    {
        return 'Inventory';
    }

    public static function permissions(): array
    {
        return [

            // 'inventory.view',
            // 'inventory.create',
            // 'inventory.update',
            // 'inventory.delete',

            // 'inventory-variant.view',
            // 'inventory-variant.create',
            // 'inventory-variant.update',
            // 'inventory-variant.delete',
        ];
    }

    public static function serviceProviders(): array
    {
        return [

            InventoryServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [];
    }
}