<?php

namespace App\Modules\Accounting;

use App\Core\Modules\Contracts\ModuleInterface;

use App\Modules\Accounting\Providers\AccountingServiceProvider;

class AccountingModule implements ModuleInterface
{
    public static function name(): string
    {
        return 'Accounting';
    }

    public static function permissions(): array
    {
        return [];
    }

    public static function serviceProviders(): array
    {
        return [
            AccountingServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [];
    }
}