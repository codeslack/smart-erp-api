<?php

namespace App\Modules\Rbac\Services\Setup\PermissionMaps;

class WarehouseManagerPermissionMap
{
    /**
     * Get default permissions for the Warehouse Manager role.
     *
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return [
            'warehouse.view',
            'warehouse.create',
            'warehouse.update',

            'product.view',
            'product-variant.view',

            'category.view',
            'brand.view',

            'unit.view',
            'product-unit.view',

            'purchase-order.view',
            'purchase.view',

            'goods-receipt-note.view',
            'goods-receipt-note.create',
            'goods-receipt-note.update',
            'goods-receipt-note.confirm',
            'goods-receipt-note.cancel',

            'opening-stock.view',
            'opening-stock.create',
            'opening-stock.update',
            'opening-stock.post',
            'opening-stock.unpost',

            'purchase-return.view',
            'sales-return.view',
        ];
    }
}
