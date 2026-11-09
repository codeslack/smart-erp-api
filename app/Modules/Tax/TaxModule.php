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
            // Tax
            'tax.view',
            'tax.create',
            'tax.update',
            'tax.delete',

            // Tax Component
            'tax-component.view',
            'tax-component.create',
            'tax-component.update',
            'tax-component.delete',

            // Tax Group
            'tax-group.view',
            'tax-group.create',
            'tax-group.update',
            'tax-group.delete',

            // Tax Group Component
            'tax-group-component.view',
            'tax-group-component.create',
            'tax-group-component.update',
            'tax-group-component.delete',

            // Tax Applicability Rule
            'tax-applicability-rule.view',
            'tax-applicability-rule.create',
            'tax-applicability-rule.update',
            'tax-applicability-rule.delete',

            // Tax Accounting Mapping
            'tax-accounting-mapping.view',
            'tax-accounting-mapping.create',
            'tax-accounting-mapping.update',
            'tax-accounting-mapping.delete',

            // Tax Registration
            'tax-registration.view',
            'tax-registration.create',
            'tax-registration.update',
            'tax-registration.delete',
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