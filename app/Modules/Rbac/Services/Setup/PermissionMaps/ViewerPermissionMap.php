<?php

namespace App\Modules\Rbac\Services\Setup\PermissionMaps;

class ViewerPermissionMap
{
    /**
     * Get default permissions for the Viewer role.
     *
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return [
            'user.view',
            'tenant.view',

            'role.view',
            'permission.view',

            'customer.view',
            'supplier.view',

            'product.view',
            'product-variant.view',
            'category.view',
            'brand.view',
            'area.view',

            'warehouse.view',
            'unit.view',
            'product-unit.view',

            'payment-term.view',
            'tax.view',
            'tax-component.view',
            'tax-group.view',
            'tax-group-component.view',
            'tax-applicability-rule.view',
            'tax-accounting-mapping.view',
            'tax-registration.view',

            'purchase-order.view',
            'purchase.view',
            'goods-receipt-note.view',
            'purchase-return.view',

            'sales-quotation.view',
            'sales-order.view',
            'sale.view',
            'sales-return.view',

            'payment.view',

            'customer-opening-bill.view',
            'supplier-opening-bill.view',

            'opening-stock.view',

            'settings.view',
        ];
    }
}
