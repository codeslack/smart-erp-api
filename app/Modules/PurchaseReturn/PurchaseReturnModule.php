<?php

namespace App\Modules\PurchaseReturn;

use App\Core\Modules\Contracts\ModuleInterface;
use App\Modules\PurchaseReturn\Providers\PurchaseReturnServiceProvider;

class PurchaseReturnModule implements ModuleInterface
{
    public static function name(): string
    {
        return 'PurchaseReturn';
    }

    public static function permissions(): array
    {
        return [
            'purchase_return.view',
            'purchase_return.create',
            'purchase_return.update',
            'purchase_return.delete',
            'purchase_return.approve',
            'purchase_return.confirm',
            'purchase_return.cancel',
        ];
    }

    public static function serviceProviders(): array
    {
        return [
            PurchaseReturnServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [];
    }
}
