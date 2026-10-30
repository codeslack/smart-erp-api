<?php

namespace App\Modules\SalesOrder\Requests;

use App\Core\Requests\BaseRequest;
use App\Core\Validation\TenantRule;
use Illuminate\Validation\Rule;

class UpdateSalesOrderRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'order_date' => ['sometimes', 'required', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],

            'customer_id' => ['nullable', 'integer', TenantRule::exists('customers', 'id')],
            'customer_uuid' => ['nullable', 'string', TenantRule::exists('customers', 'uuid')],

            'warehouse_id' => ['nullable', 'integer', TenantRule::exists('warehouses', 'id')],
            'warehouse_uuid' => ['nullable', 'string', TenantRule::exists('warehouses', 'uuid')],

            'payment_term_id' => ['nullable', 'integer', TenantRule::exists('payment_terms', 'id')],
            'payment_term_uuid' => ['nullable', 'string', TenantRule::exists('payment_terms', 'uuid')],

            'sales_quotation_id' => ['nullable', 'integer', TenantRule::exists('sales_quotations', 'id')],
            'sales_quotation_uuid' => ['nullable', 'string', TenantRule::exists('sales_quotations', 'uuid')],

            'currency_code' => ['nullable', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],

            'additional_charge_amount' => ['nullable', 'numeric', 'gte:0'],
            'rounding_adjustment' => ['nullable', 'numeric'],
            'discount_amount' => ['nullable', 'numeric', 'gte:0'],

            'notes' => ['nullable', 'string'],
            'terms_and_conditions' => ['nullable', 'string'],

            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', TenantRule::exists('products', 'id')],
            'items.*.product_uuid' => ['nullable', 'string', TenantRule::exists('products', 'uuid')],
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
            'items.*.unit_of_measure_id' => ['nullable', 'integer', TenantRule::exists('units', 'id')],

            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'gte:0'],

            'items.*.discount_type' => ['nullable', 'string', Rule::in(['fixed', 'percentage'])],
            'items.*.discount_rate' => ['nullable', 'numeric', 'gte:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'gte:0'],

            'items.*.tax_group_id' => ['nullable', 'integer', TenantRule::exists('tax_groups', 'id')],
            'items.*.tax_group_uuid' => ['nullable', 'string', TenantRule::exists('tax_groups', 'uuid')],
            'items.*.tax_rate' => ['nullable', 'numeric', 'gte:0'],
            'items.*.tax_amount' => ['nullable', 'numeric', 'gte:0'],
            'items.*.is_tax_inclusive' => ['nullable', 'boolean'],
        ];
    }
}
