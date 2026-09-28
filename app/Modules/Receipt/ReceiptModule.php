<?php

namespace App\Modules\Receipt;

use App\Core\Modules\Contracts\ModuleInterface;

use App\Modules\Receipt\Providers\ReceiptServiceProvider;

class ReceiptModule implements ModuleInterface
{
    public static function name(): string
    {
        return 'Receipt';
    }

    public static function permissions(): array
    {
        return [

            'receipt.view',
            'receipt.create',
            'receipt.update',
            'receipt.delete',
        ];
    }

    public static function serviceProviders(): array
    {
        return [

            ReceiptServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [];
    }
}