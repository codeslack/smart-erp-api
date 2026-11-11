<?php

namespace App\Modules\SalesReturn\Services\Support;

use App\Core\Exceptions\BusinessException;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Customer\Models\Customer;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Sales\Models\SaleItemBatch;
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
                    throw new BusinessException('Customer does not match the referenced sale.', 'BUSINESS_ERROR', ['customer_uuid' => ['Customer does not match the referenced sale.']]);
                }

                $data['customer_id'] = $sale->customer_id;
                $data['currency_code'] = $sale->currency_code;
                $data['exchange_rate'] = $sale->exchange_rate;
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

    /**
     * Resolve the proportional share of the source sale's header discount.
     *
     * Sale.discount_amount stores both item-level and header-level discounts,
     * so subtract the original item discounts before prorating the header part.
     */
    public function applySourceHeaderDiscount(array $headerData, array $calculatedItems): array
    {
        if (empty($headerData['sale_id'])) {
            return $headerData;
        }

        $sale = Sale::query()
            ->with('items')
            ->find($headerData['sale_id']);

        if (! $sale) {
            return $headerData;
        }

        $sourceItemDiscounts = (float) $sale->items->sum('discount_amount');
        $sourceHeaderDiscount = max(
            0,
            round((float) $sale->discount_amount - $sourceItemDiscounts, 4)
        );

        $sourceSubtotal = (float) $sale->subtotal;
        $returnSubtotal = array_sum(array_map(
            fn (array $item) => (float) ($item['subtotal'] ?? 0),
            $calculatedItems
        ));

        $discountRatio = $sourceSubtotal > 0
            ? min(1, max(0, $returnSubtotal / $sourceSubtotal))
            : 0;

        $headerData['discount_amount'] = round(
            $sourceHeaderDiscount * $discountRatio,
            4
        );

        return $headerData;
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

            if (isset($item['sale_item_uuid'])) {
                $saleItemId = SaleItem::query()
                    ->where('uuid', $item['sale_item_uuid'])
                    ->value('id');

                if (
                    isset($item['sale_item_id'])
                    && (int) $item['sale_item_id'] !== (int) $saleItemId
                ) {
                    throw new BusinessException('Sale item references do not match.');
                }

                $item['sale_item_id'] = $item['sale_item_id'] ?? $saleItemId;
                unset($item['sale_item_uuid']);
            }

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

                    if ($saleItem->product?->isBatchTracked()) {
                        foreach (($item['batches'] ?? []) as $batchIndex => $batchData) {
                            $allocation = SaleItemBatch::query()
                                ->where('sale_item_id', $saleItem->id)
                                ->where('batch_no', $batchData['batch_no'] ?? '')
                                ->first();

                            $item['batches'][$batchIndex]['product_batch_id'] = $allocation?->product_batch_id;
                        }
                    }

                    $item['product_id'] = $saleItem->product_id;
                    $item['product_variant_id'] = $saleItem->product_variant_id;
                    $item['warehouse_id'] = $item['warehouse_id'] ?? $defaultWarehouseId ?? $saleItem->warehouse_id;
                    $item['unit_id'] = $saleItem->unit_id;
                    $item['unit_price'] = (float) $saleItem->unit_price;
                    $item['line_type'] = $saleItem->line_type;
                    $item['item_name'] = $saleItem->item_name;
                    $item['sku'] = $saleItem->sku;
                    $item['description'] = $saleItem->description;

                    $originalQuantity = (float) $saleItem->quantity;
                    $originalBaseQuantity = (float) ($saleItem->base_quantity ?? $saleItem->quantity);
                    $costConversionFactor = $originalQuantity > 0
                        ? $originalBaseQuantity / $originalQuantity
                        : 1.0;
                    $item['unit_cost'] = $costConversionFactor > 0
                        ? round((float) $saleItem->cost_price / $costConversionFactor, 4)
                        : 0.0;

                    $returnQuantity = (float) ($item['returned_quantity'] ?? $item['quantity'] ?? 0);
                    $item['base_quantity'] = $originalQuantity > 0
                        ? round(
                            $originalBaseQuantity * ($returnQuantity / $originalQuantity),
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

                    $ratio = $originalQuantity > 0
                        ? min(1, max(0, $returnQuantity / $originalQuantity))
                        : 0;

                    $targetTax = round((float) $saleItem->tax_amount * $ratio, 4);
                    $item['tax_id'] = $saleItem->tax_id;
                    $item['tax_group_id'] = $saleItem->tax_group_id;
                    $item['tax_applicability_rule_id'] = $saleItem->tax_applicability_rule_id;
                    $item['tax_rate'] = 0;
                    $item['tax_amount'] = $targetTax;

                    $snapshot = $saleItem->tax_snapshot;
                    if (is_array($snapshot)) {
                        $snapshot['taxable_amount'] = round((float) ($snapshot['taxable_amount'] ?? 0) * $ratio, 4);
                        $snapshot['tax_amount'] = $targetTax;
                        foreach (($snapshot['components'] ?? []) as $index => $component) {
                            $snapshot['components'][$index]['taxable_amount'] = round((float) ($component['taxable_amount'] ?? 0) * $ratio, 4);
                            $snapshot['components'][$index]['tax_amount'] = round((float) ($component['tax_amount'] ?? 0) * $ratio, 4);
                        }

                        if (! empty($snapshot['components'])) {
                            $componentTotal = array_sum(array_map(
                                fn (array $component) => (float) ($component['tax_amount'] ?? 0),
                                $snapshot['components'],
                            ));
                            $residual = round($targetTax - $componentTotal, 4);
                            $largestIndex = array_key_first($snapshot['components']);
                            foreach ($snapshot['components'] as $index => $component) {
                                if ((float) ($component['tax_amount'] ?? 0) > (float) ($snapshot['components'][$largestIndex]['tax_amount'] ?? 0)) {
                                    $largestIndex = $index;
                                }
                            }
                            $snapshot['components'][$largestIndex]['tax_amount'] = round(
                                (float) $snapshot['components'][$largestIndex]['tax_amount'] + $residual,
                                4,
                            );
                        }
                    }
                    $item['tax_snapshot'] = $snapshot;
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
