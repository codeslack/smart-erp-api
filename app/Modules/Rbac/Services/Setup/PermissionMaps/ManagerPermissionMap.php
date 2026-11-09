<?php

namespace App\Modules\Rbac\Services\Setup\PermissionMaps;

class ManagerPermissionMap
{
    /**
     * Get default permissions for the Manager role.
     *
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return [
            'user.view',

            'customer.view',
            'customer.create',
            'customer.update',

            'supplier.view',
            'supplier.create',
            'supplier.update',

            'product.view',
            'product.create',
            'product.update',

            'product-variant.view',
            'product-variant.create',
            'product-variant.update',

            'category.view',
            'category.create',
            'category.update',

            'brand.view',
            'brand.create',
            'brand.update',

            'area.view',
            'area.create',
            'area.update',

            'warehouse.view',
            'warehouse.create',
            'warehouse.update',

            'unit.view',
            'unit.create',
            'unit.update',

            'product-unit.view',
            'product-unit.create',
            'product-unit.update',

            'payment-term.view',
            'payment-term.create',
            'payment-term.update',

            // Tax
            'tax.view',
            'tax.create',
            'tax.update',

            // Tax Component
            'tax-component.view',
            'tax-component.create',
            'tax-component.update',

            // Tax Group
            'tax-group.view',
            'tax-group.create',
            'tax-group.update',

            // Tax Group Component
            'tax-group-component.view',
            'tax-group-component.create',
            'tax-group-component.update',

            // Tax Applicability Rule
            'tax-applicability-rule.view',
            'tax-applicability-rule.create',
            'tax-applicability-rule.update',

            // Tax Accounting Mapping
            'tax-accounting-mapping.view',
            'tax-accounting-mapping.create',
            'tax-accounting-mapping.update',

            // Tax Registration
            'tax-registration.view',
            'tax-registration.create',
            'tax-registration.update',

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
            'sales-return.approve',
            'sales-return.confirm',
            'sales-return.cancel',

            'payment.view',
            'payment.create',
            'payment.update',
            'payment.confirm',
            'payment.cancel',
            'payment.reverse',

            'customer-opening-bill.view',
            'customer-opening-bill.create',
            'customer-opening-bill.update',

            'supplier-opening-bill.view',
            'supplier-opening-bill.create',
            'supplier-opening-bill.update',

            'opening-stock.view',
            'opening-stock.create',
            'opening-stock.update',
            'opening-stock.post',
            'opening-stock.unpost',

            'settings.view',
            'settings.update',
        ];
    }
}
