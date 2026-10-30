<?php

namespace App\Modules\SalesReturn;

use App\Core\Modules\Contracts\ModuleInterface;
use App\Modules\SalesReturn\Providers\SalesReturnServiceProvider;

class SalesReturnModule implements ModuleInterface
{
    public static function name(): string
    {
        return 'SalesReturn';
    }

    public static function permissions(): array
    {
        return [
            'sales_return.view',
            'sales_return.create',
            'sales_return.update',
            'sales_return.delete',
            'sales_return.approve',
            'sales_return.confirm',
            'sales_return.cancel',
        ];
    }

    public static function serviceProviders(): array
    {
        return [
            SalesReturnServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [];
    }
}
