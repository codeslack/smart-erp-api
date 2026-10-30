<?php

namespace App\Modules\SalesReturn\Services\Support;

use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Customer\Models\Customer;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Tax\Models\Tax;
use App\Modules\Unit\Models\Unit;
use App\Modules\Warehouse\Models\Warehouse;

class SalesReturnPayloadResolver
{
    public function resolveHeader(array $data): array
    {
        if (isset($data['customer_uuid']) && ! isset($data['customer_id'])) {
            $data['customer_id'] = Customer::query()
                ->where('uuid', $data['customer_uuid'])
                ->value('id');
            unset($data['customer_uuid']);
        }

        if (isset($data['sale_uuid']) && ! isset($data['sale_id'])) {
            $data['sale_id'] = Sale::query()
                ->where('uuid', $data['sale_uuid'])
                ->value('id');
            unset($data['sale_uuid']);
        }

        if (isset($data['sale_id']) && ! isset($data['customer_id'])) {
            $data['customer_id'] = Sale::query()
                ->where('id', $data['sale_id'])
                ->value('customer_id');
        }

        if (isset($data['warehouse_uuid']) && ! isset($data['warehouse_id'])) {
            $data['warehouse_id'] = Warehouse::query()
                ->where('uuid', $data['warehouse_uuid'])
                ->value('id');
            unset($data['warehouse_uuid']);
        } elseif (! isset($data['warehouse_id']) && isset($data['sale_id'])) {
            $data['warehouse_id'] = Sale::query()
                ->where('id', $data['sale_id'])
                ->value('warehouse_id');
        }

        if (isset($data['refund_account_uuid']) && ! isset($data['refund_account_id'])) {
            $data['refund_account_id'] = ChartOfAccount::query()
                ->where('uuid', $data['refund_account_uuid'])
                ->value('id');
            unset($data['refund_account_uuid']);
        }

        return $data;
    }

    public function resolveItems(array $rawItems, ?int $defaultWarehouseId = null): array
    {
        $resolved = [];

        foreach ($rawItems as $item) {
            if (isset($item['sale_item_uuid']) && ! isset($item['sale_item_id'])) {
                $saleItem = SaleItem::query()
                    ->where('uuid', $item['sale_item_uuid'])
                    ->first();

                if ($saleItem) {
                    $item['sale_item_id'] = $saleItem->id;
                    $item['product_id'] = $item['product_id'] ?? $saleItem->product_id;
                    $item['product_variant_id'] = $item['product_variant_id'] ?? $saleItem->product_variant_id;
                    $item['warehouse_id'] = $item['warehouse_id'] ?? $saleItem->warehouse_id;
                    $item['unit_id'] = $item['unit_id'] ?? $saleItem->unit_id;
                    $item['unit_price'] = $item['unit_price'] ?? (float) $saleItem->unit_price;
                    $item['unit_cost'] = $item['unit_cost'] ?? (float) $saleItem->cost_price;
                }
                unset($item['sale_item_uuid']);
            } elseif (isset($item['sale_item_id'])) {
                $saleItem = SaleItem::query()->find($item['sale_item_id']);
                if ($saleItem) {
                    $item['product_id'] = $item['product_id'] ?? $saleItem->product_id;
                    $item['product_variant_id'] = $item['product_variant_id'] ?? $saleItem->product_variant_id;
                    $item['warehouse_id'] = $item['warehouse_id'] ?? $saleItem->warehouse_id;
                    $item['unit_id'] = $item['unit_id'] ?? $saleItem->unit_id;
                    $item['unit_price'] = $item['unit_price'] ?? (float) $saleItem->unit_price;
                    $item['unit_cost'] = $item['unit_cost'] ?? (float) $saleItem->cost_price;
                }
            }

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
                $item['tax_id'] = Tax::query()
                    ->where('uuid', $item['tax_uuid'])
                    ->value('id');
                unset($item['tax_uuid']);
            }

            if (! empty($item['product_id']) && (empty($item['item_name']) || empty($item['sku']))) {
                $product = Product::query()->find($item['product_id']);
                if ($product) {
                    $item['item_name'] = $item['item_name'] ?? $product->name;
                    $item['sku'] = $item['sku'] ?? $product->sku ?? null;
                    if (! isset($item['unit_id']) && $product->unit_id) {
                        $item['unit_id'] = $product->unit_id;
                    }
                }
            }

            $item['line_type'] = $item['line_type'] ?? 'product';
            $item['item_name'] = $item['item_name'] ?? 'Item';
            $item['returned_quantity'] = $item['returned_quantity'] ?? $item['quantity'] ?? 0;

            $resolved[] = $item;
        }

        return $resolved;
    }
}
