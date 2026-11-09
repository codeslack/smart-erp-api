<?php

namespace App\Modules\Rbac\Services\Setup\PermissionMaps;

class PurchaseExecutivePermissionMap
{
    /**
     * Get default permissions for the Purchase Executive role.
     *
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return [
            'supplier.view',
            'supplier.create',
            'supplier.update',

            'product.view',
            'product-variant.view',

            'unit.view',
            'product-unit.view',

            'warehouse.view',
            'payment-term.view',

            'purchase-order.view',
            'purchase-order.create',
            'purchase-order.update',
            'purchase-order.confirm',
            'purchase-order.cancel',

            'purchase.view',
            'purchase.create',
            'purchase.update',
            'purchase.confirm',
            'purchase.cancel',

            'goods-receipt-note.view',
            'goods-receipt-note.create',
            'goods-receipt-note.update',
            'goods-receipt-note.confirm',
            'goods-receipt-note.cancel',

            'purchase-return.view',
            'purchase-return.create',
            'purchase-return.update',
            'purchase-return.approve',
            'purchase-return.confirm',
            'purchase-return.cancel',

            'supplier-opening-bill.view',
        ];
    }
}
