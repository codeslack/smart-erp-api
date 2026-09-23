<?php

namespace App\Modules\Supplier;

use App\Core\Modules\Contracts\ModuleInterface;

use App\Modules\Supplier\Providers\SupplierServiceProvider;

class SupplierModule implements ModuleInterface
{
    public static function name(): string
    {
        return 'Supplier';
    }

    public static function permissions(): array
    {
        return [

            'supplier.view',
            'supplier.create',
            'supplier.update',
            'supplier.delete',
        ];
    }

    public static function serviceProviders(): array
    {
        return [

            SupplierServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [

        ];
    }
}