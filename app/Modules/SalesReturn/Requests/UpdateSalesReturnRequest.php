<?php

namespace App\Modules\SalesReturn\Requests;

use Illuminate\Validation\Rule;
use App\Core\Validation\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use App\Modules\SalesReturn\Enums\SalesReturnCondition;
use App\Modules\SalesReturn\Enums\SalesReturnRefundType;

class UpdateSalesReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'sale_id' => [
                'sometimes',
                TenantRule::exists('sales'),
            ],

            'customer_id' => [
                'sometimes',
                TenantRule::exists('customers'),
            ],

            'return_date' => [
                'sometimes',
                'date',
            ],

            'refund_type' => [
                'sometimes',
                Rule::enum(
                    SalesReturnRefundType::class
                ),
            ],

            'return_reason' => [
                'nullable',
                'string',
                'max:255',
            ],

            'notes' => [
                'nullable',
                'string',
            ],

            'items' => [
                'sometimes',
                'array',
                'min:1',
            ],

            'items.*.sale_item_id' => [
                'required_with:items',
                Rule::exists('sale_items', 'id'),
            ],

            'items.*.product_id' => [
                'required_with:items',
                TenantRule::exists('products'),
            ],

            'items.*.warehouse_id' => [
                'required_with:items',
                TenantRule::exists('warehouses'),
            ],

            'items.*.quantity' => [
                'required_with:items',
                'numeric',
                'gt:0',
            ],

            'items.*.unit_price' => [
                'required_with:items',
                'numeric',
                'gt:0',
            ],

            'items.*.discount' => [
                'nullable',
                'numeric',
                'gte:0',
            ],

            'items.*.tax' => [
                'nullable',
                'numeric',
                'gte:0',
            ],

            'items.*.condition' => [
                'nullable',
                Rule::enum(
                    SalesReturnCondition::class
                ),
            ],

            'items.*.reason' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }
}
