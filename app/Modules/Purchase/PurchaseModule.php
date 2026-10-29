<?php

namespace App\Modules\Purchase;

use App\Core\Modules\Contracts\ModuleInterface;
use App\Modules\Purchase\Providers\PurchaseServiceProvider;

class PurchaseModule implements ModuleInterface
{
    public static function name(): string
    {
        return 'Purchase';
    }

    public static function permissions(): array
    {
        return [
            'purchase.view',
            'purchase.create',
            'purchase.update',
            'purchase.delete',
            'purchase.confirm',
            'purchase.cancel',
        ];
    }

    public static function serviceProviders(): array
    {
        return [
            PurchaseServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [];
    }
}

