<?php

namespace App\Modules\SalesQuotation\Services\Support;

use App\Modules\Customer\Models\Customer;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\Tax\Models\Tax;
use App\Modules\Unit\Models\Unit;
use App\Modules\Warehouse\Models\Warehouse;

class SalesQuotationPayloadResolver
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

            if (isset($item['unit_uuid']) && ! isset($item['unit_id'])) {
                $item['unit_id'] = Unit::query()
                    ->where('uuid', $item['unit_uuid'])
                    ->value('id');
                unset($item['unit_uuid']);
            }

            if (isset($item['tax_uuid']) && ! isset($item['tax_id'])) {
                $tax = Tax::query()->where('uuid', $item['tax_uuid'])->first();
                if ($tax) {
                    $item['tax_id'] = $tax->id;
                    $item['tax_rate'] = $item['tax_rate'] ?? (float) $tax->rate;
                }
                unset($item['tax_uuid']);
            }

            $resolved[] = $item;
        }

        return $resolved;
    }
}
