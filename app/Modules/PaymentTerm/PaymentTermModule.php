<?php

namespace App\Modules\PaymentTerm;

use App\Core\Modules\Contracts\ModuleInterface;

use App\Modules\PaymentTerm\Providers\PaymentTermServiceProvider;

use App\Modules\PaymentTerm\Services\PaymentTermSetupService;

class PaymentTermModule implements ModuleInterface
{
    public static function name(): string
    {
        return 'Payment Term';
    }

    public static function permissions(): array
    {
        return [
            'payment_term.view',
            'payment_term.create',
            'payment_term.update',
            'payment_term.delete',
        ];
    }

    public static function serviceProviders(): array
    {
        return [
            PaymentTermServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [
            PaymentTermSetupService::class,
        ];
    }
}
