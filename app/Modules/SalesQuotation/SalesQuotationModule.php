<?php

namespace App\Modules\SalesQuotation;

use App\Core\Modules\Contracts\ModuleInterface;
use App\Modules\SalesQuotation\Providers\SalesQuotationServiceProvider;

class SalesQuotationModule implements ModuleInterface
{
    public static function name(): string
    {
        return 'SalesQuotation';
    }

    public static function permissions(): array
    {
        return [
            'sales-quotation.view',
            'sales-quotation.create',
            'sales-quotation.update',
            'sales-quotation.delete',
            'sales-quotation.send',
            'sales-quotation.accept',
            'sales-quotation.reject',
            'sales-quotation.cancel',
            'sales-quotation.convert',
        ];
    }

    public static function serviceProviders(): array
    {
        return [
            SalesQuotationServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [];
    }
}
