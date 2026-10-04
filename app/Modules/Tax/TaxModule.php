<?php

namespace App\Modules\Tax;

use App\Core\Modules\Contracts\ModuleInterface;

use App\Modules\Tax\Services\TaxSetupService;
use App\Modules\Tax\Providers\TaxServiceProvider;

class TaxModule implements ModuleInterface
{
    public static function name(): string
    {
        return 'Tax';
    }

    public static function permissions(): array
    {
        return [
            'tax.view',
            'tax.create',
            'tax.update',
            'tax.delete',
        ];
    }

    public static function serviceProviders(): array
    {
        return [
            TaxServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [
            TaxSetupService::class,
        ];
    }
}