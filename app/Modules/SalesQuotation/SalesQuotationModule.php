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
            'sales_quotation.view',
            'sales_quotation.create',
            'sales_quotation.update',
            'sales_quotation.delete',
            'sales_quotation.send',
            'sales_quotation.accept',
            'sales_quotation.reject',
            'sales_quotation.cancel',
            'sales_quotation.convert',
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
