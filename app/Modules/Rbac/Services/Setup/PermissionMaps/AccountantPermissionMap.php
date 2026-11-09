<?php

namespace App\Modules\Rbac\Services\Setup\PermissionMaps;

class AccountantPermissionMap
{
    /**
     * Get default permissions for the Accountant role.
     *
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return [
            'customer.view',
            'supplier.view',

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

            'payment-term.view',

            'payment.view',
            'payment.create',
            'payment.update',
            'payment.confirm',
            'payment.cancel',

            'purchase-order.view',
            'purchase.view',
            'sale.view',

            'purchase-return.view',
            'sales-return.view',

            'customer-opening-bill.view',
            'customer-opening-bill.create',
            'customer-opening-bill.update',
            'customer-opening-bill.delete',

            'supplier-opening-bill.view',
            'supplier-opening-bill.create',
            'supplier-opening-bill.update',
            'supplier-opening-bill.delete',

            'settings.view',
        ];
    }
}
