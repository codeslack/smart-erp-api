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
use App\Modules\SalesOrder\Models\SalesOrderItem;
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
        $this->resolveUuidReference($data, 'customer_uuid', 'customer_id', Customer::class, 'Customer');
        $this->resolveUuidReference($data, 'warehouse_uuid', 'warehouse_id', Warehouse::class, 'Warehouse');
        $this->resolveUuidReference($data, 'sales_order_uuid', 'sales_order_id', SalesOrder::class, 'Sales order');
        $this->resolveUuidReference($data, 'delivery_note_uuid', 'delivery_note_id', DeliveryNote::class, 'Delivery note');
        $this->resolveUuidReference($data, 'sales_quotation_uuid', 'sales_quotation_id', SalesQuotation::class, 'Sales quotation');
        $this->resolveUuidReference($data, 'payment_term_uuid', 'payment_term_id', PaymentTerm::class, 'Payment term');

        return $data;
    }

    protected function resolveUuidReference(
        array &$data,
        string $uuidKey,
        string $idKey,
        string $modelClass,
        string $label,
        ?string $errorKey = null
    ): void {
        if (! isset($data[$uuidKey])) {
            return;
        }

        $resolvedId = $modelClass::query()
            ->where('uuid', $data[$uuidKey])
            ->value('id');

        if (isset($data[$idKey]) && (int) $data[$idKey] !== (int) $resolvedId) {
            $field = $errorKey ?? $uuidKey;

            throw new BusinessException(
                "{$label} ID and UUID references do not match.",
                'BUSINESS_ERROR',
                [$field => ["{$label} ID and UUID references do not match."]]
            );
        }

        $data[$idKey] = $data[$idKey] ?? $resolvedId;
        unset($data[$uuidKey]);
    }

    public function resolveItems(array $rawItems, ?int $defaultWarehouseId = null, ?int $salesOrderId = null): array
    {
        $resolved = [];

        foreach ($rawItems as $index => $item) {
            $this->resolveUuidReference($item, 'product_uuid', 'product_id', Product::class, 'Product', "items.{$index}.product_uuid");
            $this->resolveUuidReference($item, 'product_variant_uuid', 'product_variant_id', ProductVariant::class, 'Product variant', "items.{$index}.product_variant_uuid");
            $this->resolveUuidReference($item, 'warehouse_uuid', 'warehouse_id', Warehouse::class, 'Warehouse', "items.{$index}.warehouse_uuid");

            if (! isset($item['warehouse_id']) && $defaultWarehouseId) {
                $item['warehouse_id'] = $defaultWarehouseId;
            }

            $this->resolveUuidReference($item, 'unit_uuid', 'unit_id', Unit::class, 'Unit', "items.{$index}.unit_uuid");
            $this->resolveUuidReference($item, 'tax_uuid', 'tax_id', Tax::class, 'Tax', "items.{$index}.tax_uuid");
            $this->resolveUuidReference($item, 'sales_order_item_uuid', 'sales_order_item_id', SalesOrderItem::class, 'Sales order item', "items.{$index}.sales_order_item_uuid");

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

            if (isset($item['sales_order_item_id'])) {
                if (! $salesOrderId) {
                    throw new BusinessException(
                        'A sales order must be selected when linking an invoice line to an order item.',
                        'BUSINESS_ERROR',
                        ["items.{$index}.sales_order_item_uuid" => ['A sales order is required for this order item.']]
                    );
                }

                $orderItem = SalesOrderItem::query()
                    ->whereKey($item['sales_order_item_id'])
                    ->where('sales_order_id', $salesOrderId)
                    ->first();

                if (! $orderItem) {
                    throw new BusinessException(
                        'The selected sales order item does not belong to the selected sales order.',
                        'BUSINESS_ERROR',
                        ["items.{$index}.sales_order_item_uuid" => ['The order item does not belong to the selected sales order.']]
                    );
                }

                $saleProductId = isset($item['product_id']) ? (int) $item['product_id'] : null;
                $orderProductId = $orderItem->product_id !== null ? (int) $orderItem->product_id : null;
                $saleVariantId = isset($item['product_variant_id']) ? (int) $item['product_variant_id'] : null;
                $orderVariantId = $orderItem->product_variant_id !== null ? (int) $orderItem->product_variant_id : null;
                $saleUnitId = isset($item['unit_id']) ? (int) $item['unit_id'] : null;
                $orderUnitId = $orderItem->unit_of_measure_id !== null ? (int) $orderItem->unit_of_measure_id : null;

                if (
                    $saleProductId !== $orderProductId
                    || $saleVariantId !== $orderVariantId
                    || $saleUnitId !== $orderUnitId
                ) {
                    throw new BusinessException(
                        'The invoice line product, variant, and unit must match the linked sales order item.',
                        'BUSINESS_ERROR',
                        ["items.{$index}.sales_order_item_uuid" => ['The invoice line does not match the linked order item.']]
                    );
                }
            }

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
