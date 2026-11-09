<?php

namespace App\Modules\Rbac\Services\Setup\PermissionMaps;

class SalesExecutivePermissionMap
{
    /**
     * Get default permissions for the Sales Executive role.
     *
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return [
            'customer.view',
            'customer.create',
            'customer.update',

            'product.view',
            'product-variant.view',

            'unit.view',
            'product-unit.view',

            'sales-quotation.view',
            'sales-quotation.create',
            'sales-quotation.update',
            'sales-quotation.send',
            'sales-quotation.accept',
            'sales-quotation.reject',
            'sales-quotation.cancel',
            'sales-quotation.convert',

            'sales-order.view',
            'sales-order.create',
            'sales-order.update',
            'sales-order.confirm',
            'sales-order.cancel',

            'sale.view',
            'sale.create',
            'sale.update',
            'sale.confirm',
            'sale.cancel',

            'sales-return.view',
            'sales-return.create',
            'sales-return.update',
        ];
    }
}
