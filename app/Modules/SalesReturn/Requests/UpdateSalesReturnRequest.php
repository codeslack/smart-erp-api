<?php

namespace App\Modules\SalesReturn\Requests;

use App\Core\Requests\BaseRequest;
use App\Core\Validation\TenantRule;
use App\Modules\SalesReturn\Enums\SalesReturnCondition;
use App\Modules\SalesReturn\Enums\SalesReturnRefundType;
use Illuminate\Validation\Rule;

class UpdateSalesReturnRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'return_date' => ['sometimes', 'required', 'date'],

            'customer_id' => ['nullable', 'integer', TenantRule::exists('customers', 'id')],
            'customer_uuid' => ['nullable', 'string', TenantRule::exists('customers', 'uuid')],

            'warehouse_id' => ['nullable', 'integer', TenantRule::exists('warehouses', 'id')],
            'warehouse_uuid' => ['nullable', 'string', TenantRule::exists('warehouses', 'uuid')],

            'refund_account_id' => ['nullable', 'integer', TenantRule::exists('chart_of_accounts', 'id')],
            'refund_account_uuid' => ['nullable', 'string', TenantRule::exists('chart_of_accounts', 'uuid')],

            'refund_type' => ['nullable', Rule::enum(SalesReturnRefundType::class)],
            'return_reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'rounding_amount' => ['nullable', 'numeric'],

            'items' => ['sometimes', 'array', 'min:1'],
            'items.*' => [
                'required',
                'array:sale_item_id,sale_item_uuid,product_id,product_uuid,product_variant_id,product_variant_uuid,warehouse_id,warehouse_uuid,returned_quantity,quantity,unit_price,unit_cost,discount_type,discount_rate,discount_amount,tax_id,tax_uuid,tax_rate,tax_amount,condition,reason,batches,serials',
            ],
            'items.*.sale_item_id' => ['nullable', 'integer', TenantRule::exists('sale_items', 'id')],
            'items.*.sale_item_uuid' => ['nullable', 'string', TenantRule::exists('sale_items', 'uuid')],
            'items.*.product_id' => ['nullable', 'integer', TenantRule::exists('products', 'id')],
            'items.*.product_uuid' => ['nullable', 'string', TenantRule::exists('products', 'uuid')],
            'items.*.product_variant_id' => ['nullable', 'integer', TenantRule::exists('product_variants', 'id')],
            'items.*.product_variant_uuid' => ['nullable', 'string', TenantRule::exists('product_variants', 'uuid')],
            'items.*.warehouse_id' => ['nullable', 'integer', TenantRule::exists('warehouses', 'id')],
            'items.*.warehouse_uuid' => ['nullable', 'string', TenantRule::exists('warehouses', 'uuid')],

            'items.*.returned_quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'gte:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'gte:0'],

            'items.*.discount_type' => ['nullable', 'string', Rule::in(['fixed', 'percentage'])],
            'items.*.discount_rate' => ['nullable', 'numeric', 'gte:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'gte:0'],

            'items.*.tax_id' => ['nullable', 'integer', TenantRule::exists('taxes', 'id')],
            'items.*.tax_uuid' => ['nullable', 'string', TenantRule::exists('taxes', 'uuid')],
            'items.*.tax_rate' => ['nullable', 'numeric', 'gte:0'],
            'items.*.tax_amount' => ['nullable', 'numeric', 'gte:0'],

            'items.*.condition' => ['nullable', Rule::enum(SalesReturnCondition::class)],
            'items.*.reason' => ['nullable', 'string', 'max:255'],

            'items.*.batches' => ['nullable', 'array'],
            'items.*.batches.*' => ['required', 'array:batch_no,quantity'],
            'items.*.batches.*.batch_no' => ['required', 'string', 'max:100'],
            'items.*.batches.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.serials' => ['nullable', 'array'],
            'items.*.serials.*' => [
                function ($attribute, $value, $fail) {
                    if (is_array($value)) {
                        if (array_diff(array_keys($value), ['serial_number', 'serial_no']) !== []) {
                            $fail('Serial entries may only contain a serial number.');
                            return;
                        }

                        $serialNumber = $value['serial_number'] ?? $value['serial_no'] ?? null;
                    } else {
                        $serialNumber = $value;
                    }

                    if (! is_string($serialNumber) || trim($serialNumber) === '') {
                        $fail('A valid serial number is required.');
                    }
                },
            ],
        ];
    }
}
