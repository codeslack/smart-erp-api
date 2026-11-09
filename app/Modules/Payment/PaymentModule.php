<?php

namespace App\Modules\Payment;

use App\Core\Modules\Contracts\ModuleInterface;

use App\Modules\Payment\Providers\PaymentServiceProvider;

class PaymentModule implements ModuleInterface
{
    public static function name(): string
    {
        return 'Payment';
    }

    public static function permissions(): array
    {
        return [

            'payment.view',
            'payment.create',
            'payment.update',
            'payment.delete',

            'payment.confirm',
            'payment.cancel',
            'payment.reverse',
        ];
    }

    public static function serviceProviders(): array
    {
        return [

            PaymentServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [];
    }
}