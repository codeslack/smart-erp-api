<?php

namespace App\Modules\Sales;

use App\Core\Modules\Contracts\ModuleInterface;
use App\Modules\Sales\Providers\SaleServiceProvider;

class SalesModule implements ModuleInterface
{
    public static function name(): string
    {
        return 'Sales';
    }

    public static function permissions(): array
    {
        return [
            'sale.view',
            'sale.create',
            'sale.update',
            'sale.delete',
            'sale.confirm',
            'sale.cancel',
        ];
    }

    public static function serviceProviders(): array
    {
        return [
            SaleServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [];
    }
}
