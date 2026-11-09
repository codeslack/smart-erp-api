<?php

namespace App\Modules\PurchaseOrder;

use App\Core\Modules\Contracts\ModuleInterface;

use App\Modules\PurchaseOrder\Providers\PurchaseOrderServiceProvider;

class PurchaseOrderModule implements ModuleInterface
{
    public static function name(): string
    {
        return 'Purchase Order';
    }

    public static function permissions(): array
    {
        return [

            'purchase-order.view',
            'purchase-order.create',
            'purchase-order.update',
            'purchase-order.delete',
            'purchase-order.confirm',
            'purchase-order.cancel',
        ];
    }

    public static function serviceProviders(): array
    {
        return [

            PurchaseOrderServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [];
    }
}