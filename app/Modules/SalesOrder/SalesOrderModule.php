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
            'sales-order.view',
            'sales-order.create',
            'sales-order.update',
            'sales-order.delete',
            'sales-order.confirm',
            'sales-order.cancel',
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
