<?php

namespace App\Modules\Customer;

use App\Core\Modules\Contracts\ModuleInterface;

use App\Modules\Customer\Providers\CustomerServiceProvider;

class CustomerModule implements ModuleInterface
{
    public static function name(): string
    {
        return 'Customer';
    }

    public static function permissions(): array
    {
        return [

            'customer.view',
            'customer.create',
            'customer.update',
            'customer.delete',
        ];
    }

    public static function serviceProviders(): array
    {
        return [

            CustomerServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [

        ];
    }
}