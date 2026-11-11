<?php

namespace App\Modules\SalesReturn\Services\Support;

use App\Core\Exceptions\BusinessException;
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
        if (isset($data['customer_uuid'])) {
            $customerId = Customer::query()
                ->where('uuid', $data['customer_uuid'])
                ->value('id');

            if (isset($data['customer_id']) && (int) $data['customer_id'] !== (int) $customerId) {
                throw new BusinessException('Customer references do not match.');
            }

            $data['customer_id'] = $data['customer_id'] ?? $customerId;
            unset($data['customer_uuid']);
        }

        if (isset($data['sale_uuid'])) {
            $saleId = Sale::query()
                ->where('uuid', $data['sale_uuid'])
                ->value('id');

            if (isset($data['sale_id']) && (int) $data['sale_id'] !== (int) $saleId) {
                throw new BusinessException('Sale references do not match.');
            }

            $data['sale_id'] = $data['sale_id'] ?? $saleId;
            unset($data['sale_uuid']);
        }

        if (isset($data['sale_id'])) {
            $sale = Sale::query()->find($data['sale_id']);

            if ($sale) {
                if (
                    isset($data['customer_id'])
                    && (int) $data['customer_id'] !== (int) $sale->customer_id
                ) {
                    throw new BusinessException('Customer does not match the referenced sale.');
                }

                $data['customer_id'] = $sale->customer_id;
            }
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
            if (isset($item['product_uuid'])) {
                $productId = Product::query()->where('uuid', $item['product_uuid'])->value('id');
                if (isset($item['product_id']) && (int) $item['product_id'] !== (int) $productId) {
                    throw new BusinessException('Product reference does not match the original sale item.');
                }
                $item['product_id'] = $item['product_id'] ?? $productId;
                unset($item['product_uuid']);
            }

            if (isset($item['product_variant_uuid'])) {
                $variantId = ProductVariant::query()->where('uuid', $item['product_variant_uuid'])->value('id');
                if (isset($item['product_variant_id']) && (int) $item['product_variant_id'] !== (int) $variantId) {
                    throw new BusinessException('Product variant does not match the original sale item.');
                }
                $item['product_variant_id'] = $item['product_variant_id'] ?? $variantId;
                unset($item['product_variant_uuid']);
            }

            if (isset($item['warehouse_uuid'])) {
                $warehouseId = Warehouse::query()->where('uuid', $item['warehouse_uuid'])->value('id');
                if (isset($item['warehouse_id']) && (int) $item['warehouse_id'] !== (int) $warehouseId) {
                    throw new BusinessException('Warehouse references do not match.');
                }
                $item['warehouse_id'] = $item['warehouse_id'] ?? $warehouseId;
                unset($item['warehouse_uuid']);
            }

            if (isset($item['unit_uuid'])) {
                $item['unit_id'] = $item['unit_id'] ?? Unit::query()->where('uuid', $item['unit_uuid'])->value('id');
                unset($item['unit_uuid']);
            }

            if (isset($item['tax_uuid'])) {
                $item['tax_id'] = $item['tax_id'] ?? Tax::query()->where('uuid', $item['tax_uuid'])->value('id');
                unset($item['tax_uuid']);
            }

            if (isset($item['sale_item_uuid']) && ! isset($item['sale_item_id'])) {
                $item['sale_item_id'] = SaleItem::query()
                    ->where('uuid', $item['sale_item_uuid'])
                    ->value('id');
            }
            unset($item['sale_item_uuid']);

            if (isset($item['sale_item_id'])) {
                $saleItem = SaleItem::query()->find($item['sale_item_id']);

                if ($saleItem) {
                    if (
                        isset($item['product_id'])
                        && (int) $item['product_id'] !== (int) $saleItem->product_id
                    ) {
                        throw new BusinessException('Product does not match the original sale item.');
                    }

                    if (
                        ! empty($item['product_variant_id'])
                        && (int) $item['product_variant_id'] !== (int) $saleItem->product_variant_id
                    ) {
                        throw new BusinessException('Product variant does not match the original sale item.');
                    }

                    $item['product_id'] = $saleItem->product_id;
                    $item['product_variant_id'] = $saleItem->product_variant_id;
                    $item['warehouse_id'] = $item['warehouse_id'] ?? $defaultWarehouseId ?? $saleItem->warehouse_id;
                    $item['unit_id'] = $saleItem->unit_id;
                    $item['unit_price'] = (float) $saleItem->unit_price;
                    $item['unit_cost'] = (float) $saleItem->cost_price;
                    $item['line_type'] = $saleItem->line_type;
                    $item['item_name'] = $saleItem->item_name;
                    $item['sku'] = $saleItem->sku;
                    $item['description'] = $saleItem->description;

                    $originalQuantity = (float) $saleItem->quantity;
                    $returnQuantity = (float) ($item['returned_quantity'] ?? $item['quantity'] ?? 0);
                    $item['base_quantity'] = $originalQuantity > 0
                        ? round(
                            (float) ($saleItem->base_quantity ?? $saleItem->quantity)
                                * ($returnQuantity / $originalQuantity),
                            4
                        )
                        : 0.0;
                    $discountType = $saleItem->discount_type ?: 'fixed';
                    $discountRate = (float) $saleItem->discount_rate;
                    $originalDiscount = (float) $saleItem->discount_amount;

                    $item['discount_type'] = $discountType;
                    if ($discountType === 'percentage') {
                        $item['discount_rate'] = $discountRate;
                        $item['discount_amount'] = 0;
                    } else {
                        $fixedDiscount = $originalDiscount > 0 ? $originalDiscount : $discountRate;
                        $item['discount_rate'] = 0;
                        $item['discount_amount'] = $originalQuantity > 0
                            ? round($fixedDiscount * ($returnQuantity / $originalQuantity), 4)
                            : 0;
                    }

                    $item['tax_id'] = $saleItem->tax_id;
                    $item['tax_rate'] = (float) $saleItem->tax_rate;
                    $item['tax_amount'] = $item['tax_rate'] > 0
                        ? 0
                        : ($originalQuantity > 0
                            ? round((float) $saleItem->tax_amount * ($returnQuantity / $originalQuantity), 4)
                            : 0);
                }
            }

            if (! isset($item['warehouse_id']) && $defaultWarehouseId !== null) {
                $item['warehouse_id'] = $defaultWarehouseId;
            }

            $item['line_type'] = $item['line_type'] ?? 'product';
            $item['item_name'] = $item['item_name'] ?? 'Item';
            $item['returned_quantity'] = $item['returned_quantity'] ?? $item['quantity'] ?? 0;
            unset($item['quantity']);

            $resolved[] = $item;
        }

        return $resolved;
    }
}
