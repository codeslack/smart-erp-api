<?php

namespace App\Modules\SalesOrder\Services\Support;

use App\Modules\Customer\Models\Customer;
use App\Modules\PaymentTerm\Models\PaymentTerm;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\SalesQuotation\Models\SalesQuotation;
use App\Modules\Tax\Models\TaxGroup;
use App\Modules\Unit\Models\Unit;
use App\Modules\Warehouse\Models\Warehouse;

class SalesOrderPayloadResolver
{
    public function resolveHeader(array $data): array
    {
        if (isset($data['customer_uuid']) && ! isset($data['customer_id'])) {
            $data['customer_id'] = Customer::query()
                ->where('uuid', $data['customer_uuid'])
                ->value('id');
            unset($data['customer_uuid']);
        }

        if (isset($data['warehouse_uuid']) && ! isset($data['warehouse_id'])) {
            $data['warehouse_id'] = Warehouse::query()
                ->where('uuid', $data['warehouse_uuid'])
                ->value('id');
            unset($data['warehouse_uuid']);
        }

        if (isset($data['payment_term_uuid']) && ! isset($data['payment_term_id'])) {
            $data['payment_term_id'] = PaymentTerm::query()
                ->where('uuid', $data['payment_term_uuid'])
                ->value('id');
            unset($data['payment_term_uuid']);
        }

        if (isset($data['sales_quotation_uuid']) && ! isset($data['sales_quotation_id'])) {
            $data['sales_quotation_id'] = SalesQuotation::query()
                ->where('uuid', $data['sales_quotation_uuid'])
                ->value('id');
            unset($data['sales_quotation_uuid']);
        }

        return $data;
    }

    public function resolveItems(array $rawItems, ?int $defaultWarehouseId = null): array
    {
        $resolved = [];

        foreach ($rawItems as $item) {
            if (isset($item['product_uuid']) && ! isset($item['product_id'])) {
                $item['product_id'] = Product::query()
                    ->where('uuid', $item['product_uuid'])
                    ->value('id');
                unset($item['product_uuid']);
            }

            if (isset($item['product_variant_uuid']) && ! isset($item['product_variant_id'])) {
                $item['product_variant_id'] = ProductVariant::query()
                    ->where('uuid', $item['product_variant_uuid'])
                    ->value('id');
                unset($item['product_variant_uuid']);
            }

            if (isset($item['warehouse_uuid']) && ! isset($item['warehouse_id'])) {
                $item['warehouse_id'] = Warehouse::query()
                    ->where('uuid', $item['warehouse_uuid'])
                    ->value('id');
                unset($item['warehouse_uuid']);
            } elseif (! isset($item['warehouse_id']) && $defaultWarehouseId) {
                $item['warehouse_id'] = $defaultWarehouseId;
            }

            if (isset($item['unit_uuid']) && ! isset($item['unit_of_measure_id'])) {
                $item['unit_of_measure_id'] = Unit::query()
                    ->where('uuid', $item['unit_uuid'])
                    ->value('id');
                unset($item['unit_uuid']);
            } elseif (isset($item['unit_id']) && ! isset($item['unit_of_measure_id'])) {
                $item['unit_of_measure_id'] = $item['unit_id'];
                unset($item['unit_id']);
            }

            if (isset($item['tax_group_uuid']) && ! isset($item['tax_group_id'])) {
                $item['tax_group_id'] = TaxGroup::query()
                    ->where('uuid', $item['tax_group_uuid'])
                    ->value('id');
                unset($item['tax_group_uuid']);
            }

            if (! empty($item['product_id']) && (empty($item['item_name']) || empty($item['sku']))) {
                $product = Product::query()->find($item['product_id']);
                if ($product) {
                    $item['item_name'] = $item['item_name'] ?? $product->name;
                    $item['sku'] = $item['sku'] ?? $product->sku ?? null;
                    if (! isset($item['unit_of_measure_id']) && $product->unit_id) {
                        $item['unit_of_measure_id'] = $product->unit_id;
                    }
                }
            }

            $item['line_type'] = $item['line_type'] ?? 'product';
            $item['item_name'] = $item['item_name'] ?? 'Item';

            $resolved[] = $item;
        }

        return $resolved;
    }
}
