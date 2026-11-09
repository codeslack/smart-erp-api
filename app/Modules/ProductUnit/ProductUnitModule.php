<?php

namespace App\Modules\ProductUnit;

use App\Core\Modules\Contracts\ModuleInterface;

use App\Modules\ProductUnit\Providers\ProductUnitServiceProvider;

class ProductUnitModule implements ModuleInterface
{
    public static function name(): string
    {
        return 'ProductUnit';
    }

    public static function permissions(): array
    {
        return [
            'product-unit.view',
            'product-unit.create',
            'product-unit.update',
            'product-unit.delete',
        ];
    }

    public static function serviceProviders(): array
    {
        return [
            ProductUnitServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [];
    }
}