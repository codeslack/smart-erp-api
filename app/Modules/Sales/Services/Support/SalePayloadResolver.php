<?php

namespace App\Modules\Sales\Services\Support;

use App\Core\Exceptions\BusinessException;
use App\Modules\ProductUnit\Services\ProductUnitService;
use App\Modules\Customer\Models\Customer;
use App\Modules\DeliveryNote\Models\DeliveryNote;
use App\Modules\PaymentTerm\Models\PaymentTerm;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\SalesOrder\Models\SalesOrder;
use App\Modules\SalesQuotation\Models\SalesQuotation;
use App\Modules\Tax\Models\Tax;
use App\Modules\Unit\Models\Unit;
use App\Modules\Warehouse\Models\Warehouse;

class SalePayloadResolver
{
    public function __construct(
        protected ProductUnitService $productUnitService
    ) {}

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

        if (isset($data['sales_order_uuid']) && ! isset($data['sales_order_id'])) {
            $data['sales_order_id'] = SalesOrder::query()
                ->where('uuid', $data['sales_order_uuid'])
                ->value('id');
            unset($data['sales_order_uuid']);
        }

        if (isset($data['delivery_note_uuid']) && ! isset($data['delivery_note_id'])) {
            $data['delivery_note_id'] = DeliveryNote::query()
                ->where('uuid', $data['delivery_note_uuid'])
                ->value('id');
            unset($data['delivery_note_uuid']);
        }

        if (isset($data['sales_quotation_uuid']) && ! isset($data['sales_quotation_id'])) {
            $data['sales_quotation_id'] = SalesQuotation::query()
                ->where('uuid', $data['sales_quotation_uuid'])
                ->value('id');
            unset($data['sales_quotation_uuid']);
        }

        if (isset($data['payment_term_uuid']) && ! isset($data['payment_term_id'])) {
            $data['payment_term_id'] = PaymentTerm::query()
                ->where('uuid', $data['payment_term_uuid'])
                ->value('id');
            unset($data['payment_term_uuid']);
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

            $product = ! empty($item['product_id'])
                ? Product::query()->find((int) $item['product_id'])
                : null;

            if (
                $product
                && ! isset($item['unit_id'])
                && $product->unit_id
            ) {
                $item['unit_id'] = (int) $product->unit_id;
            }

            $productUnit = null;
            $conversionFactor = 1.0;

            if ($product && $product->requiresStock() && isset($item['unit_id'])) {
                $productUnit = $this->productUnitService
                    ->findByProductAndUnit(
                        $product->id,
                        (int) $item['unit_id']
                    );

                if (
                    ! $productUnit
                    && (int) ($product->unit_id ?? 0) === (int) $item['unit_id']
                ) {
                    $productUnit = $this->productUnitService
                        ->syncBaseUnit($product);
                }

                if (! $productUnit) {
                    throw new BusinessException(
                        'The selected sales unit is not configured for this product.'
                    );
                }

                $this->productUnitService
                    ->ensureSalesUnit($productUnit);

                $conversionFactor = (float) $productUnit->conversion_factor;
            }

            if ($conversionFactor <= 0) {
                throw new BusinessException(
                    'The selected sales unit has an invalid conversion factor.'
                );
            }

            $item['base_quantity'] = round(
                (float) ($item['quantity'] ?? 0) * $conversionFactor,
                4
            );

            if (isset($item['batches']) && is_array($item['batches'])) {
                foreach ($item['batches'] as $index => $batch) {
                    $item['batches'][$index]['base_quantity'] = round(
                        (float) ($batch['quantity'] ?? 0) * $conversionFactor,
                        4
                    );
                }
            }

            $item['line_type'] = $item['line_type'] ?? 'product';
            $item['item_name'] = $item['item_name'] ?? 'Item';

            $resolved[] = $item;
        }

        return $resolved;
    }
}
