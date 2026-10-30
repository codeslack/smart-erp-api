<?php

namespace App\Modules\SalesOrder;

use App\Core\Modules\Contracts\ModuleInterface;
use App\Modules\SalesOrder\Providers\SalesOrderServiceProvider;

class SalesOrderModule implements ModuleInterface
{
    public static function name(): string
    {
        return 'Sales Order';
    }

    public static function permissions(): array
    {
        return [
            'sales_order.view',
            'sales_order.create',
            'sales_order.update',
            'sales_order.delete',
            'sales_order.confirm',
            'sales_order.cancel',
        ];
    }

    public static function serviceProviders(): array
    {
        return [
            SalesOrderServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [];
    }
}
