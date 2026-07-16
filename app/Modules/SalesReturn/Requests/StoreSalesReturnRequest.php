<?php

namespace App\Modules\SalesReturn\Requests;

use Illuminate\Validation\Rule;
use App\Core\Validation\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use App\Modules\SalesReturn\Enums\SalesReturnCondition;
use App\Modules\SalesReturn\Enums\SalesReturnRefundType;

class StoreSalesReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'sale_id' => [
                'required',
                TenantRule::exists('sales'),
            ],

            'customer_id' => [
                'required',
                TenantRule::exists('customers'),
            ],

            'return_date' => [
                'required',
                'date',
            ],

            'refund_type' => [
                'nullable',
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
                'required',
                'array',
                'min:1',
            ],

            'items.*.sale_item_id' => [
                'required',
                Rule::exists('sale_items', 'id'),
            ],

            'items.*.product_id' => [
                'required',
                TenantRule::exists('products'),
            ],

            'items.*.warehouse_id' => [
                'required',
                TenantRule::exists('warehouses'),
            ],

            'items.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'items.*.unit_price' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'items.*.discount' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'items.*.tax' => [
                'nullable',
                'numeric',
                'min:0',
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
