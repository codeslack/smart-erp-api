<?php

namespace App\Modules\Rbac\Services\Setup;

use App\Modules\Rbac\Enums\RoleEnum;

class DefaultRolePermissionMap
{
    public function all(): array
    {
        return [
            RoleEnum::MANAGER->value => [
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

                'product_unit.view',
                'product_unit.create',
                'product_unit.update',

                'payment_term.view',
                'payment_term.create',
                'payment_term.update',

                'tax.view',
                'tax.create',
                'tax.update',

                'purchase_order.view',
                'purchase_order.create',
                'purchase_order.update',
                'purchase_order.confirm',
                'purchase_order.cancel',

                'purchase.view',
                'purchase.create',
                'purchase.update',
                'purchase.confirm',
                'purchase.cancel',

                'goods_receipt_note.view',
                'goods_receipt_note.create',
                'goods_receipt_note.update',
                'goods_receipt_note.confirm',
                'goods_receipt_note.cancel',

                'purchase_return.view',
                'purchase_return.create',
                'purchase_return.update',
                'purchase_return.approve',
                'purchase_return.confirm',
                'purchase_return.cancel',

                'sales_quotation.view',
                'sales_quotation.create',
                'sales_quotation.update',
                'sales_quotation.send',
                'sales_quotation.accept',
                'sales_quotation.reject',
                'sales_quotation.cancel',
                'sales_quotation.convert',

                'sales_order.view',
                'sales_order.create',
                'sales_order.update',
                'sales_order.confirm',
                'sales_order.cancel',

                'sale.view',
                'sale.create',
                'sale.update',
                'sale.confirm',
                'sale.cancel',

                'sales_return.view',
                'sales_return.create',
                'sales_return.update',
                'sales_return.approve',
                'sales_return.confirm',
                'sales_return.cancel',

                'payment.view',
                'payment.create',
                'payment.update',

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
            ],

            RoleEnum::ACCOUNTANT->value => [
                'customer.view',
                'supplier.view',

                'tax.view',
                'tax.create',
                'tax.update',

                'payment_term.view',

                'payment.view',
                'payment.create',
                'payment.update',

                'purchase_order.view',
                'purchase.view',
                'sale.view',

                'purchase_return.view',
                'sales_return.view',

                'customer-opening-bill.view',
                'customer-opening-bill.create',
                'customer-opening-bill.update',
                'customer-opening-bill.delete',

                'supplier-opening-bill.view',
                'supplier-opening-bill.create',
                'supplier-opening-bill.update',
                'supplier-opening-bill.delete',

                'settings.view',
            ],

            RoleEnum::SALES_EXECUTIVE->value => [
                'customer.view',
                'customer.create',
                'customer.update',

                'product.view',
                'product-variant.view',

                'unit.view',
                'product_unit.view',

                'sales_quotation.view',
                'sales_quotation.create',
                'sales_quotation.update',
                'sales_quotation.send',
                'sales_quotation.accept',
                'sales_quotation.reject',
                'sales_quotation.cancel',
                'sales_quotation.convert',

                'sales_order.view',
                'sales_order.create',
                'sales_order.update',
                'sales_order.confirm',
                'sales_order.cancel',

                'sale.view',
                'sale.create',
                'sale.update',
                'sale.confirm',
                'sale.cancel',

                'sales_return.view',
                'sales_return.create',
                'sales_return.update',
            ],

            RoleEnum::PURCHASE_EXECUTIVE->value => [
                'supplier.view',
                'supplier.create',
                'supplier.update',

                'product.view',
                'product-variant.view',

                'unit.view',
                'product_unit.view',

                'warehouse.view',
                'payment_term.view',

                'purchase_order.view',
                'purchase_order.create',
                'purchase_order.update',
                'purchase_order.confirm',
                'purchase_order.cancel',

                'purchase.view',
                'purchase.create',
                'purchase.update',
                'purchase.confirm',
                'purchase.cancel',

                'goods_receipt_note.view',
                'goods_receipt_note.create',
                'goods_receipt_note.update',
                'goods_receipt_note.confirm',
                'goods_receipt_note.cancel',

                'purchase_return.view',
                'purchase_return.create',
                'purchase_return.update',
                'purchase_return.approve',
                'purchase_return.confirm',
                'purchase_return.cancel',

                'supplier-opening-bill.view',
            ],

            RoleEnum::WAREHOUSE_MANAGER->value => [
                'warehouse.view',
                'warehouse.create',
                'warehouse.update',

                'product.view',
                'product-variant.view',

                'category.view',
                'brand.view',

                'unit.view',
                'product_unit.view',

                'purchase_order.view',
                'purchase.view',

                'goods_receipt_note.view',
                'goods_receipt_note.create',
                'goods_receipt_note.update',
                'goods_receipt_note.confirm',
                'goods_receipt_note.cancel',

                'opening-stock.view',
                'opening-stock.create',
                'opening-stock.update',
                'opening-stock.post',
                'opening-stock.unpost',

                'purchase_return.view',
                'sales_return.view',
            ],

            RoleEnum::VIEWER->value => [
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
                'product_unit.view',

                'payment_term.view',
                'tax.view',

                'purchase_order.view',
                'purchase.view',
                'goods_receipt_note.view',
                'purchase_return.view',

                'sales_quotation.view',
                'sales_order.view',
                'sale.view',
                'sales_return.view',

                'payment.view',

                'customer-opening-bill.view',
                'supplier-opening-bill.view',

                'opening-stock.view',

                'settings.view',
            ],
        ];
    }
}