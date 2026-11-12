<?php

namespace App\Modules\Purchase\Services\Support;

use App\Core\Exceptions\BusinessException;
use App\Modules\ProductUnit\Services\ProductUnitService;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\PaymentTerm\Models\PaymentTerm;
use App\Modules\PurchaseOrder\Models\PurchaseOrder;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\Unit\Models\Unit;
use App\Modules\Tax\Models\Tax;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItem;
use App\Modules\PurchaseOrder\Models\PurchaseOrderItem;

class PurchasePayloadResolver
{
    public function __construct(
        protected ProductUnitService $productUnitService
    ) {}

    public function resolveHeader(array $data): array
    {
        if (isset($data['supplier_uuid']) && ! isset($data['supplier_id'])) {
            $data['supplier_id'] = Supplier::query()->where('uuid', $data['supplier_uuid'])->value('id');
            unset($data['supplier_uuid']);
        }

        if (isset($data['warehouse_uuid']) && ! isset($data['warehouse_id'])) {
            $data['warehouse_id'] = Warehouse::query()->where('uuid', $data['warehouse_uuid'])->value('id');
            unset($data['warehouse_uuid']);
        }

        if (isset($data['purchase_order_uuid']) && ! isset($data['purchase_order_id'])) {
            $data['purchase_order_id'] = PurchaseOrder::query()->where('uuid', $data['purchase_order_uuid'])->value('id');
            unset($data['purchase_order_uuid']);
        }

        if (isset($data['goods_receipt_note_uuid']) && ! isset($data['goods_receipt_note_id'])) {
            $data['goods_receipt_note_id'] = GoodsReceiptNote::query()->where('uuid', $data['goods_receipt_note_uuid'])->value('id');
            unset($data['goods_receipt_note_uuid']);
        }

        if (isset($data['payment_term_uuid']) && ! isset($data['payment_term_id'])) {
            $data['payment_term_id'] = PaymentTerm::query()->where('uuid', $data['payment_term_uuid'])->value('id');
            unset($data['payment_term_uuid']);
        }

        if (isset($data['goods_receipt_note_id'])) {
            $grn = GoodsReceiptNote::query()
                ->with('purchaseOrder')
                ->find((int) $data['goods_receipt_note_id']);

            if ($grn && $grn->purchaseOrder) {
                $purchaseOrder = $grn->purchaseOrder;

                if (
                    isset($data['purchase_order_id'])
                    && (int) $data['purchase_order_id'] !== (int) $purchaseOrder->id
                ) {
                    throw new BusinessException(
                        'Purchase order does not match the linked goods receipt note.'
                    );
                }

                $sourceCurrency = strtoupper(
                    (string) $purchaseOrder->currency_code
                );
                $sourceRate = (string) $purchaseOrder->exchange_rate;

                if (
                    isset($data['currency_code'])
                    && strtoupper((string) $data['currency_code']) !== $sourceCurrency
                ) {
                    throw new BusinessException(
                        'A GRN-linked purchase must use the purchase order currency.'
                    );
                }

                if (
                    isset($data['exchange_rate'])
                    && bccomp((string) $data['exchange_rate'], $sourceRate, 6) !== 0
                ) {
                    throw new BusinessException(
                        'A GRN-linked purchase must use the purchase order exchange rate.'
                    );
                }

                $data['purchase_order_id'] = $purchaseOrder->id;
                $data['currency_code'] = $sourceCurrency;
                $data['exchange_rate'] = $sourceRate;
            }
        }

        return $data;
    }

    public function resolveItems(array $rawItems, ?int $defaultWarehouseId = null): array
    {
        $resolved = [];

        foreach ($rawItems as $item) {
            if (isset($item['product_uuid']) && ! isset($item['product_id'])) {
                $item['product_id'] = Product::query()->where('uuid', $item['product_uuid'])->value('id');
                unset($item['product_uuid']);
            }

            if (isset($item['product_variant_uuid']) && ! isset($item['product_variant_id'])) {
                $item['product_variant_id'] = ProductVariant::query()->where('uuid', $item['product_variant_uuid'])->value('id');
                unset($item['product_variant_uuid']);
            }

            if (isset($item['warehouse_uuid']) && ! isset($item['warehouse_id'])) {
                $item['warehouse_id'] = Warehouse::query()->where('uuid', $item['warehouse_uuid'])->value('id');
                unset($item['warehouse_uuid']);
            }

            if (! isset($item['warehouse_id']) && $defaultWarehouseId !== null) {
                $item['warehouse_id'] = $defaultWarehouseId;
            }

            if (isset($item['goods_receipt_note_item_uuid']) && ! isset($item['goods_receipt_note_item_id'])) {
                $item['goods_receipt_note_item_id'] = GoodsReceiptNoteItem::query()->where('uuid', $item['goods_receipt_note_item_uuid'])->value('id');
                unset($item['goods_receipt_note_item_uuid']);
            }

            if (isset($item['purchase_order_item_uuid']) && ! isset($item['purchase_order_item_id'])) {
                $item['purchase_order_item_id'] = PurchaseOrderItem::query()->where('uuid', $item['purchase_order_item_uuid'])->value('id');
                unset($item['purchase_order_item_uuid']);
            }

            if (isset($item['unit_uuid']) && ! isset($item['unit_id'])) {
                $item['unit_id'] = Unit::query()->where('uuid', $item['unit_uuid'])->value('id');
                unset($item['unit_uuid']);
            }

            if (isset($item['tax_uuid']) && ! isset($item['tax_id'])) {
                $item['tax_id'] = Tax::query()->where('uuid', $item['tax_uuid'])->value('id');
                unset($item['tax_uuid']);
            }

            // Derive product and variant from GRN item if not explicitly supplied
            if (isset($item['goods_receipt_note_item_id']) && ! isset($item['product_id'])) {
                $grnItem = GoodsReceiptNoteItem::query()->find($item['goods_receipt_note_item_id']);
                if ($grnItem) {
                    $item['product_id'] = $grnItem->product_id;
                    $item['product_variant_id'] = $item['product_variant_id'] ?? $grnItem->product_variant_id;
                    $item['unit_id'] = $item['unit_id'] ?? $grnItem->unit_id;
                    $item['warehouse_id'] = $item['warehouse_id'] ?? $grnItem->warehouse_id;
                    $item['unit_cost'] = $item['unit_cost'] ?? $grnItem->unit_cost;
                }
            }

            $product = isset($item['product_id'])
                ? Product::query()->find((int) $item['product_id'])
                : null;

            $productUnit = null;
            $conversionFactor = 1.0;
            $grnItem = isset($item['goods_receipt_note_item_id'])
                ? GoodsReceiptNoteItem::query()->find(
                    (int) $item['goods_receipt_note_item_id']
                )
                : null;

            if (
                $grnItem
                && ! isset($item['unit_id'])
            ) {
                $item['unit_id'] = $grnItem->unit_id;
            }

            $usesGrnUnitSnapshot = $grnItem
                && isset($item['unit_id'])
                && (int) $grnItem->unit_id === (int) $item['unit_id'];

            if ($usesGrnUnitSnapshot) {
                $grnQuantity = (float) $grnItem->quantity_received;
                $grnBaseQuantity = (float) (
                    $grnItem->base_quantity
                    ?? $grnItem->quantity_received
                );

                $conversionFactor = $grnQuantity > 0
                    ? $grnBaseQuantity / $grnQuantity
                    : 1.0;
            } elseif ($product && $product->requiresStock()) {
                if (! isset($item['unit_id']) && $product->unit_id) {
                    $item['unit_id'] = (int) $product->unit_id;
                }

                if (isset($item['unit_id'])) {
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
                            'The selected purchase unit is not configured for this product.'
                        );
                    }

                    $this->productUnitService
                        ->ensurePurchaseUnit($productUnit);

                    $conversionFactor = (float) $productUnit->conversion_factor;
                }
            }

            if ($conversionFactor <= 0) {
                throw new BusinessException(
                    'The selected purchase unit has an invalid conversion factor.'
                );
            }

            $quantity = (float) ($item['quantity'] ?? 0);
            $unitCost = (float) ($item['unit_cost'] ?? 0);

            // These inventory values are server-derived; client values are never trusted.
            $item['base_quantity'] = round(
                $quantity * $conversionFactor,
                4
            );

            $item['base_unit_cost'] = $productUnit
                ? $this->productUnitService->toBaseUnitCost(
                    $productUnit,
                    $unitCost
                )
                : round($unitCost / $conversionFactor, 8);

            if (isset($item['batches']) && is_array($item['batches'])) {
                foreach ($item['batches'] as $index => $batch) {
                    $item['batches'][$index]['base_quantity'] = round(
                        (float) ($batch['quantity'] ?? 0) * $conversionFactor,
                        4
                    );
                }
            }

            $resolved[] = $item;
        }

        return $resolved;
    }

    public function resolveCharges(array $rawCharges, float $itemsSubtotal = 0.0): array
    {
        $resolved = [];

        foreach ($rawCharges as $index => $charge) {
            if (isset($charge['chart_of_account_uuid']) && ! isset($charge['chart_of_account_id'])) {
                $charge['chart_of_account_id'] = ChartOfAccount::query()
                    ->where('uuid', $charge['chart_of_account_uuid'])
                    ->value('id');
                unset($charge['chart_of_account_uuid']);
            }

            if (isset($charge['tax_uuid']) && ! isset($charge['tax_id'])) {
                $charge['tax_id'] = Tax::query()
                    ->where('uuid', $charge['tax_uuid'])
                    ->value('id');
                unset($charge['tax_uuid']);
            }

            $calculationMethod = $charge['calculation_method'] ?? 'fixed';
            $calculationValue = (float) ($charge['calculation_value'] ?? 0);
            $amount = (float) ($charge['amount'] ?? 0);

            if ($calculationMethod === 'percentage' && $amount === 0.0 && $calculationValue > 0) {
                $amount = round($itemsSubtotal * ($calculationValue / 100), 4);
            } elseif ($calculationMethod === 'fixed' && $amount === 0.0 && $calculationValue > 0) {
                $amount = round($calculationValue, 4);
            }

            $taxRate = (float) ($charge['tax_rate'] ?? 0);
            $taxAmount = (float) ($charge['tax_amount'] ?? 0);

            if ($taxRate > 0 && $taxAmount === 0.0) {
                $taxAmount = round($amount * ($taxRate / 100), 4);
            }

            $charge['calculation_method'] = $calculationMethod;
            $charge['calculation_value'] = $calculationValue;
            $charge['amount'] = $amount;
            $charge['tax_rate'] = $taxRate;
            $charge['tax_amount'] = $taxAmount;
            $charge['sort_order'] = (int) ($charge['sort_order'] ?? $index);

            $resolved[] = $charge;
        }

        return $resolved;
    }
}

