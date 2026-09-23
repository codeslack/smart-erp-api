<?php

namespace App\Modules\SupplierOpeningBill;

use App\Core\Modules\Contracts\ModuleInterface;
use App\Modules\SupplierOpeningBill\Providers\SupplierOpeningBillServiceProvider;

class SupplierOpeningBillModule implements ModuleInterface
{
    public static function name(): string
    {
        return 'Supplier Opening Bill';
    }

    public static function permissions(): array
    {
        return [
            'supplier-opening-bill.view',
            'supplier-opening-bill.create',
            'supplier-opening-bill.update',
            'supplier-opening-bill.delete',
        ];
    }

    public static function serviceProviders(): array
    {
        return [
            SupplierOpeningBillServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [];
    }
}
