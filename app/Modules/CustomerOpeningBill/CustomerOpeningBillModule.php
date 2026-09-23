<?php

namespace App\Modules\CustomerOpeningBill;

use App\Core\Modules\Contracts\ModuleInterface;
use App\Modules\CustomerOpeningBill\Providers\CustomerOpeningBillServiceProvider;

class CustomerOpeningBillModule implements ModuleInterface
{
    public static function name(): string
    {
        return 'Customer Opening Bill';
    }

    public static function permissions(): array
    {
        return [
            'customer-opening-bill.view',
            'customer-opening-bill.create',
            'customer-opening-bill.update',
            'customer-opening-bill.delete',
        ];
    }

    public static function serviceProviders(): array
    {
        return [
            CustomerOpeningBillServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [];
    }
}