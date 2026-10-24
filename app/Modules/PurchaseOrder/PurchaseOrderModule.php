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

            'purchase_order.view',
            'purchase_order.create',
            'purchase_order.update',
            'purchase_order.delete',
            'purchase_order.confirm',
            'purchase_order.cancel',
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