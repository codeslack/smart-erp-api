<?php

namespace App\Modules\PurchaseReturn\Services\Support;

use App\Core\Exceptions\BusinessException;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseItem;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Tax\Models\Tax;
use App\Modules\Unit\Models\Unit;
use App\Modules\Warehouse\Models\Warehouse;

class PurchaseReturnPayloadResolver
{
    public function resolveHeader(array $data): array
    {
        if (isset($data['purchase_uuid'])) {
            $purchaseId = Purchase::query()
                ->where('uuid', $data['purchase_uuid'])
                ->value('id');

            if (isset($data['purchase_id']) && (int) $data['purchase_id'] !== (int) $purchaseId) {
                throw new BusinessException('Purchase references do not match.');
            }

            $data['purchase_id'] = $data['purchase_id'] ?? $purchaseId;
            unset($data['purchase_uuid']);
        }

        if (isset($data['supplier_uuid'])) {
            $supplierId = Supplier::query()
                ->where('uuid', $data['supplier_uuid'])
                ->value('id');

            if (isset($data['supplier_id']) && (int) $data['supplier_id'] !== (int) $supplierId) {
                throw new BusinessException('Supplier references do not match.');
            }

            $data['supplier_id'] = $data['supplier_id'] ?? $supplierId;
            unset($data['supplier_uuid']);
        }

        if (isset($data['purchase_id'])) {
            $purchaseSupplierId = Purchase::query()
                ->whereKey($data['purchase_id'])
                ->value('supplier_id');

            if (
                $purchaseSupplierId !== null
                && isset($data['supplier_id'])
                && (int) $data['supplier_id'] !== (int) $purchaseSupplierId
            ) {
                throw new BusinessException('Supplier does not match the referenced purchase.', 'BUSINESS_ERROR', ['supplier_uuid' => ['Supplier does not match the referenced purchase.']]);
            }

            $data['supplier_id'] = $purchaseSupplierId ?? ($data['supplier_id'] ?? null);
        }

        if (isset($data['refund_account_uuid']) && ! isset($data['refund_account_id'])) {
            $data['refund_account_id'] = ChartOfAccount::query()->where('uuid', $data['refund_account_uuid'])->value('id');
            unset($data['refund_account_uuid']);
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

            if (isset($item['unit_uuid']) && ! isset($item['unit_id'])) {
                $item['unit_id'] = Unit::query()->where('uuid', $item['unit_uuid'])->value('id');
                unset($item['unit_uuid']);
            }

            if (isset($item['tax_uuid']) && ! isset($item['tax_id'])) {
                $item['tax_id'] = Tax::query()->where('uuid', $item['tax_uuid'])->value('id');
                unset($item['tax_uuid']);
            }

            if (isset($item['purchase_item_uuid']) && ! isset($item['purchase_item_id'])) {
                $item['purchase_item_id'] = PurchaseItem::query()->where('uuid', $item['purchase_item_uuid'])->value('id');
                unset($item['purchase_item_uuid']);
            }

            if (isset($item['purchase_item_id'])) {
                $purchaseItem = PurchaseItem::query()->find($item['purchase_item_id']);
                if ($purchaseItem) {
                    $item['product_id'] = $item['product_id'] ?? $purchaseItem->product_id;
                    $item['product_variant_id'] = $item['product_variant_id'] ?? $purchaseItem->product_variant_id;
                    $item['warehouse_id'] = $item['warehouse_id'] ?? $purchaseItem->warehouse_id ?? $defaultWarehouseId;
                    $item['unit_id'] = $purchaseItem->unit_id;
                    $item['unit_cost'] = (float) $purchaseItem->unit_cost;

                    $purchaseQuantity = (float) $purchaseItem->quantity;
                    $returnedQuantity = (float) ($item['returned_quantity'] ?? 0);
                    $quantityRatio = $purchaseQuantity > 0
                        ? $returnedQuantity / $purchaseQuantity
                        : 0.0;

                    $purchaseBaseQuantity = (float) ($purchaseItem->base_quantity ?? $purchaseItem->quantity);
                    $item['base_quantity'] = round($purchaseBaseQuantity * $quantityRatio, 4);

                    // A return must reverse the original purchase line values, not client overrides.
                    $item['discount_amount'] = round(
                        (float) $purchaseItem->discount_amount * $quantityRatio,
                        4
                    );
                    $item['tax_id'] = $purchaseItem->tax_id;
                    $item['tax_rate'] = (float) $purchaseItem->tax_rate;
                    $item['tax_amount'] = round(
                        (float) $purchaseItem->tax_amount * $quantityRatio,
                        4
                    );
                }
            }

            if (! isset($item['warehouse_id']) && $defaultWarehouseId !== null) {
                $item['warehouse_id'] = $defaultWarehouseId;
            }

            $resolved[] = $item;
        }

        return $resolved;
    }
}
