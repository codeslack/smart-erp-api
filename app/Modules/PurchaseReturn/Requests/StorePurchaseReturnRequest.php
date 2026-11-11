<?php

namespace App\Modules\PurchaseReturn\Requests;

use App\Core\Requests\BaseRequest;
use App\Core\Validation\TenantRule;
use App\Modules\Purchase\Models\PurchaseItem;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnCondition;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnRefundType;
use Illuminate\Validation\Rule;

class StorePurchaseReturnRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        $items = $this->input('items', []);
        if (is_array($items)) {
            foreach ($items as $index => $item) {
                if (isset($item['quantity']) && ! isset($item['returned_quantity'])) {
                    $items[$index]['returned_quantity'] = $item['quantity'];
                }
                if (isset($item['discount']) && ! isset($item['discount_amount'])) {
                    $items[$index]['discount_amount'] = $item['discount'];
                }
                if (isset($item['tax']) && ! isset($item['tax_amount'])) {
                    $items[$index]['tax_amount'] = $item['tax'];
                }
                if (! isset($items[$index]['unit_cost'])) {
                    if (isset($item['purchase_item_id'])) {
                        $cost = PurchaseItem::query()->where('id', $item['purchase_item_id'])->value('unit_cost');
                        if ($cost !== null) {
                            $items[$index]['unit_cost'] = $cost;
                        }
                    } elseif (isset($item['purchase_item_uuid'])) {
                        $cost = PurchaseItem::query()->where('uuid', $item['purchase_item_uuid'])->value('unit_cost');
                        if ($cost !== null) {
                            $items[$index]['unit_cost'] = $cost;
                        }
                    }
                }
            }
            $this->merge(['items' => $items]);
        }
    }

    public function rules(): array
    {
        return [
            'purchase_id' => ['required_without:purchase_uuid', 'nullable', 'integer', TenantRule::exists('purchases', 'id')],
            'purchase_uuid' => ['required_without:purchase_id', 'nullable', 'string', TenantRule::exists('purchases', 'uuid')],
            'supplier_id' => ['nullable', 'integer', TenantRule::exists('suppliers', 'id')],
            'supplier_uuid' => ['nullable', 'string', TenantRule::exists('suppliers', 'uuid')],
            'return_no' => ['nullable', 'string', 'max:50'],
            'return_date' => ['required', 'date'],
            'refund_type' => ['nullable', Rule::enum(PurchaseReturnRefundType::class)],
            'refund_account_id' => ['nullable', 'integer', TenantRule::exists('chart_of_accounts', 'id')],
            'refund_account_uuid' => ['nullable', 'string', TenantRule::exists('chart_of_accounts', 'uuid')],
            'return_reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'rounding_amount' => ['nullable', 'numeric'],

            'items' => ['required', 'array', 'min:1'],
            'items.*' => [
                'required',
                'array:purchase_item_id,purchase_item_uuid,product_id,product_uuid,product_variant_id,product_variant_uuid,warehouse_id,warehouse_uuid,unit_id,unit_uuid,returned_quantity,quantity,unit_cost,discount_amount,discount,tax_id,tax_uuid,tax_rate,tax_amount,tax,condition,reason,batches,serials',
            ],
            'items.*.purchase_item_id' => ['nullable', 'integer', TenantRule::exists('purchase_items', 'id')],
            'items.*.purchase_item_uuid' => ['nullable', 'string', TenantRule::exists('purchase_items', 'uuid')],
            'items.*.product_id' => ['nullable', 'integer', TenantRule::exists('products', 'id')],
            'items.*.product_uuid' => ['nullable', 'string', TenantRule::exists('products', 'uuid')],
            'items.*.product_variant_id' => ['nullable', 'integer', TenantRule::exists('product_variants', 'id')],
            'items.*.product_variant_uuid' => ['nullable', 'string', TenantRule::exists('product_variants', 'uuid')],
            'items.*.warehouse_id' => ['nullable', 'integer', TenantRule::exists('warehouses', 'id')],
            'items.*.warehouse_uuid' => ['nullable', 'string', TenantRule::exists('warehouses', 'uuid')],
            'items.*.unit_id' => ['nullable', 'integer', TenantRule::exists('units', 'id')],
            'items.*.unit_uuid' => ['nullable', 'string', TenantRule::exists('units', 'uuid')],
            'items.*.returned_quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'gte:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_id' => ['nullable', 'integer', TenantRule::exists('taxes', 'id')],
            'items.*.tax_uuid' => ['nullable', 'string', TenantRule::exists('taxes', 'uuid')],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.condition' => ['nullable', Rule::enum(PurchaseReturnCondition::class)],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
            'items.*.batches' => ['nullable', 'array'],
            'items.*.batches.*.batch_no' => ['required_with:items.*.batches', 'string', 'max:100'],
            'items.*.batches.*.quantity' => ['required_with:items.*.batches', 'numeric', 'gt:0'],
            'items.*.serials' => ['nullable', 'array'],
        ];
    }
}
