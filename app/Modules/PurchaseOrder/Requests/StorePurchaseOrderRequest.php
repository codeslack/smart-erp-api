<?php

namespace App\Modules\PurchaseOrder\Requests;

use Illuminate\Validation\Rule;

use App\Core\Requests\BaseRequest;
use App\Core\Validation\TenantRule;

class StorePurchaseOrderRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'order_date' => [
                'required',
                'date',
            ],

            'expected_delivery_date' => [
                'nullable',
                'date',
                'after_or_equal:order_date',
            ],

            'supplier_id' => [
                'required',
                'integer',
                TenantRule::exists('suppliers'),
            ],

            'warehouse_id' => [
                'required',
                'integer',
                TenantRule::exists('warehouses'),
            ],

            'payment_term_id' => [
                'nullable',
                'integer',
                TenantRule::exists('payment_terms'),
            ],

            'currency_code' => [
                'required',
                'string',
                'size:3',
            ],

            'exchange_rate' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'notes' => [
                'nullable',
                'string',
            ],

            'terms_and_conditions' => [
                'nullable',
                'string',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.product_id' => [
                'nullable',
                'integer',
                TenantRule::exists('products'),
            ],

            'items.*.product_variant_id' => [
                'nullable',
                TenantRule::exists(
                    'product_variants'
                ),
            ],

            'items.*.warehouse_id' => [
                'nullable',
                'integer',
                TenantRule::exists('warehouses'),
            ],

            'items.*.line_type' => [
                'required',
                'string',
                Rule::in([
                    'product',
                    'service',
                    'expense',
                ]),
            ],

            'items.*.item_name' => [
                'required',
                'string',
                'max:255',
            ],

            'items.*.sku' => [
                'nullable',
                'string',
                'max:100',
            ],

            'items.*.description' => [
                'nullable',
                'string',
            ],

            'items.*.unit_of_measure_id' => [
                'nullable',
                'integer',
                TenantRule::exists('units'),
            ],

            'items.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'items.*.unit_price' => [
                'required',
                'numeric',
                'gte:0',
            ],

            'items.*.discount_type' => [
                'required',
                'string',
                Rule::in([
                    'fixed',
                    'percentage',
                ]),
            ],

            'items.*.discount_rate' => [
                'nullable',
                'numeric',
                'gte:0',
            ],

            'items.*.tax_group_id' => [
                'nullable',
                'integer',
                TenantRule::exists('tax_groups'),
            ],

            'items.*.is_tax_inclusive' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}