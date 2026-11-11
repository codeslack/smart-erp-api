<?php

namespace App\Modules\Sales\Requests;

use App\Core\Requests\BaseRequest;
use App\Core\Validation\TenantRule;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'sale_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],

            'customer_id' => ['required_without:customer_uuid', 'nullable', 'integer', TenantRule::exists('customers', 'id')],
            'customer_uuid' => ['required_without:customer_id', 'nullable', 'string', TenantRule::exists('customers', 'uuid')],

            'warehouse_id' => ['required_without:warehouse_uuid', 'nullable', 'integer', TenantRule::exists('warehouses', 'id')],
            'warehouse_uuid' => ['required_without:warehouse_id', 'nullable', 'string', TenantRule::exists('warehouses', 'uuid')],

            'sales_order_id' => ['nullable', 'integer', TenantRule::exists('sales_orders', 'id')],
            'sales_order_uuid' => ['nullable', 'string', TenantRule::exists('sales_orders', 'uuid')],

            'delivery_note_id' => ['nullable', 'integer', TenantRule::exists('delivery_notes', 'id')],
            'delivery_note_uuid' => ['nullable', 'string', TenantRule::exists('delivery_notes', 'uuid')],

            'payment_term_id' => ['nullable', 'integer', TenantRule::exists('payment_terms', 'id')],
            'payment_term_uuid' => ['nullable', 'string', TenantRule::exists('payment_terms', 'uuid')],

            'sale_no' => ['nullable', 'string', 'max:50'],
            'customer_reference_number' => ['nullable', 'string', 'max:100'],

            'currency_code' => ['nullable', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],

            'discount_amount' => ['nullable', 'numeric', 'gte:0'],
            'discount' => ['nullable', 'numeric', 'gte:0'],
            'charges_subtotal' => ['nullable', 'numeric', 'gte:0'],
            'charges_tax_amount' => ['nullable', 'numeric', 'gte:0'],
            'rounding_amount' => ['nullable', 'numeric'],

            'notes' => ['nullable', 'string'],
            'terms_and_conditions' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*' => [
                'required',
                'array:product_id,product_uuid,product_variant_id,product_variant_uuid,warehouse_id,warehouse_uuid,line_type,item_name,sku,description,unit_id,unit_uuid,quantity,unit_price,discount_type,discount_rate,discount_amount,tax_id,tax_uuid,tax_rate,tax_amount,batches,serials',
            ],
            'items.*.product_id' => ['required_without:items.*.product_uuid', 'nullable', 'integer', TenantRule::exists('products', 'id')],
            'items.*.product_uuid' => ['required_without:items.*.product_id', 'nullable', 'string', TenantRule::exists('products', 'uuid')],
            'items.*.product_variant_id' => ['nullable', 'integer', TenantRule::exists('product_variants', 'id')],
            'items.*.product_variant_uuid' => ['nullable', 'string', TenantRule::exists('product_variants', 'uuid')],
            'items.*.warehouse_id' => ['nullable', 'integer', TenantRule::exists('warehouses', 'id')],
            'items.*.warehouse_uuid' => ['nullable', 'string', TenantRule::exists('warehouses', 'uuid')],

            'items.*.line_type' => ['nullable', 'string', Rule::in(['product', 'service', 'expense'])],
            'items.*.item_name' => ['nullable', 'string', 'max:255'],
            'items.*.sku' => ['nullable', 'string', 'max:100'],
            'items.*.description' => ['nullable', 'string'],

            'items.*.unit_id' => ['nullable', 'integer', TenantRule::exists('units', 'id')],
            'items.*.unit_uuid' => ['nullable', 'string', TenantRule::exists('units', 'uuid')],

            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'gte:0'],

            'items.*.discount_type' => ['nullable', 'string', Rule::in(['fixed', 'percentage'])],
            'items.*.discount_rate' => ['nullable', 'numeric', 'gte:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'gte:0'],

            'items.*.tax_id' => ['nullable', 'integer', TenantRule::exists('taxes', 'id')],
            'items.*.tax_uuid' => ['nullable', 'string', TenantRule::exists('taxes', 'uuid')],
            'items.*.tax_rate' => ['nullable', 'numeric', 'gte:0'],
            'items.*.tax_amount' => ['nullable', 'numeric', 'gte:0'],

            'items.*.batches' => ['nullable', 'array'],
            'items.*.batches.*' => [
                'required',
                'array:batch_no,quantity',
            ],
            'items.*.batches.*.batch_no' => ['required_with:items.*.batches', 'string', 'max:100'],
            'items.*.batches.*.quantity' => ['required_with:items.*.batches', 'numeric', 'gt:0'],

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
