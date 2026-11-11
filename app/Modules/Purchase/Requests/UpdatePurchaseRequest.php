<?php

namespace App\Modules\Purchase\Requests;

use App\Core\Requests\BaseRequest;
use App\Core\Validation\TenantRule;

class UpdatePurchaseRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'supplier_id' => ['sometimes', 'nullable', 'integer', TenantRule::exists('suppliers', 'id')],
            'supplier_uuid' => ['sometimes', 'nullable', 'string', TenantRule::exists('suppliers', 'uuid')],
            'warehouse_id' => ['nullable', 'integer', TenantRule::exists('warehouses', 'id')],
            'warehouse_uuid' => ['nullable', 'string', TenantRule::exists('warehouses', 'uuid')],
            'payment_term_id' => ['nullable', 'integer', TenantRule::exists('payment_terms', 'id')],
            'payment_term_uuid' => ['nullable', 'string', TenantRule::exists('payment_terms', 'uuid')],
            'supplier_invoice_number' => ['nullable', 'string', 'max:100'],
            'supplier_invoice_date' => ['nullable', 'date'],
            'purchase_date' => ['sometimes', 'required', 'date'],
            'due_date' => ['nullable', 'date'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'tax_calculation_mode' => ['nullable', 'in:exclusive,inclusive'],
            'discount_type' => ['nullable', 'in:none,fixed,percentage'],
            'discount_rate' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'rounding_amount' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string', 'max:1000'],

            'items' => ['sometimes', 'required', 'array', 'min:1'],
            'items.*' => [
                'required',
                'array:product_id,product_uuid,product_variant_id,product_variant_uuid,warehouse_id,warehouse_uuid,goods_receipt_note_item_id,goods_receipt_note_item_uuid,purchase_order_item_id,purchase_order_item_uuid,unit_id,unit_uuid,quantity,unit_cost,discount_type,discount_rate,discount_percentage,discount_amount,tax_id,tax_uuid,tax_rate,tax_amount,batches,serials',
            ],
            'items.*.product_id' => ['nullable', 'integer', TenantRule::exists('products', 'id')],
            'items.*.product_uuid' => ['nullable', 'string', TenantRule::exists('products', 'uuid')],
            'items.*.product_variant_id' => ['nullable', 'integer', TenantRule::exists('product_variants', 'id')],
            'items.*.product_variant_uuid' => ['nullable', 'string', TenantRule::exists('product_variants', 'uuid')],
            'items.*.warehouse_id' => ['nullable', 'integer', TenantRule::exists('warehouses', 'id')],
            'items.*.warehouse_uuid' => ['nullable', 'string', TenantRule::exists('warehouses', 'uuid')],
            'items.*.goods_receipt_note_item_id' => ['nullable', 'integer', TenantRule::exists('goods_receipt_note_items', 'id')],
            'items.*.goods_receipt_note_item_uuid' => ['nullable', 'string', TenantRule::exists('goods_receipt_note_items', 'uuid')],
            'items.*.purchase_order_item_id' => ['nullable', 'integer', TenantRule::exists('purchase_order_items', 'id')],
            'items.*.purchase_order_item_uuid' => ['nullable', 'string', TenantRule::exists('purchase_order_items', 'uuid')],
            'items.*.unit_id' => ['nullable', 'integer', TenantRule::exists('units', 'id')],
            'items.*.unit_uuid' => ['nullable', 'string', TenantRule::exists('units', 'uuid')],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required_with:items', 'numeric', 'gte:0'],
            'items.*.discount_type' => ['nullable', 'in:none,fixed,percentage'],
            'items.*.discount_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_id' => ['nullable', 'integer', TenantRule::exists('taxes', 'id')],
            'items.*.tax_uuid' => ['nullable', 'string', TenantRule::exists('taxes', 'uuid')],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.batches' => ['nullable', 'array'],
            'items.*.batches.*' => [
                'required',
                'array:batch_no,quantity,manufacturing_date,expiry_date',
            ],
            'items.*.batches.*.batch_no' => ['required_with:items.*.batches', 'string', 'max:100'],
            'items.*.batches.*.quantity' => ['required_with:items.*.batches', 'numeric', 'gt:0'],
            'items.*.batches.*.manufacturing_date' => ['nullable', 'date'],
            'items.*.batches.*.expiry_date' => ['nullable', 'date'],
            'items.*.serials' => ['nullable', 'array'],
            'items.*.serials.*' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (is_string($value) || is_int($value)) {
                        $serial = trim((string) $value);
                    } elseif (is_array($value)) {
                        $keys = array_keys($value);

                        if (
                            count($keys) !== 1
                            || ! in_array($keys[0], ['serial_number', 'serial_no'], true)
                        ) {
                            $fail('Each serial entry must contain only serial_number or serial_no.');
                            return;
                        }

                        $serial = trim((string) ($value[$keys[0]] ?? ''));
                    } else {
                        $fail('Each serial entry must be a non-empty serial number.');
                        return;
                    }

                    if ($serial === '' || strlen($serial) > 100) {
                        $fail('Serial numbers must contain between 1 and 100 characters.');
                    }
                },
            ],

            'charges' => ['sometimes', 'nullable', 'array'],
            'charges.*' => [
                'required',
                'array:charge_type,name,calculation_method,calculation_value,amount,tax_id,tax_uuid,tax_rate,tax_amount,treatment,allocation_method,affects_inventory_cost,chart_of_account_id,chart_of_account_uuid,sort_order',
            ],
            'charges.*.charge_type' => ['required_with:charges', 'string', 'max:50'],
            'charges.*.name' => ['required_with:charges', 'string', 'max:150'],
            'charges.*.calculation_method' => ['nullable', 'in:fixed,percentage'],
            'charges.*.calculation_value' => ['nullable', 'numeric', 'min:0'],
            'charges.*.amount' => ['nullable', 'numeric', 'min:0'],
            'charges.*.tax_id' => ['nullable', 'integer', TenantRule::exists('taxes', 'id')],
            'charges.*.tax_uuid' => ['nullable', 'string', TenantRule::exists('taxes', 'uuid')],
            'charges.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
            'charges.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'charges.*.treatment' => ['nullable', 'in:expense,landed_cost'],
            'charges.*.allocation_method' => ['nullable', 'in:by_value,by_quantity,equal'],
            'charges.*.affects_inventory_cost' => ['nullable', 'boolean'],
            'charges.*.chart_of_account_id' => ['nullable', 'integer', TenantRule::exists('chart_of_accounts', 'id')],
            'charges.*.chart_of_account_uuid' => ['nullable', 'string', TenantRule::exists('chart_of_accounts', 'uuid')],
            'charges.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
