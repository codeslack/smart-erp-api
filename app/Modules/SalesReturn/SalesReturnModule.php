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
            'sales-return.view',
            'sales-return.create',
            'sales-return.update',
            'sales-return.delete',
            'sales-return.approve',
            'sales-return.confirm',
            'sales-return.cancel',
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
