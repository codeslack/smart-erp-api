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
            'purchase-return.view',
            'purchase-return.create',
            'purchase-return.update',
            'purchase-return.delete',
            'purchase-return.approve',
            'purchase-return.confirm',
            'purchase-return.cancel',
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
