<?php

namespace App\Modules\PurchaseReturn\Requests;

use Illuminate\Validation\Rule;
use App\Core\Validation\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnCondition;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnRefundType;

class UpdatePurchaseReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'purchase_id' => [
                'sometimes',
                TenantRule::exists(
                    'purchases'
                ),
            ],

            'supplier_id' => [
                'sometimes',
                TenantRule::exists(
                    'suppliers'
                ),
            ],

            'return_date' => [
                'sometimes',
                'date',
            ],

            'refund_type' => [
                'sometimes',
                Rule::enum(
                    PurchaseReturnRefundType::class
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
                'required',
                'array',
                'min:1',
            ],

            'items.*.purchase_item_id' => [
                'required_with:items',
                Rule::exists('purchase_items', 'id'),
            ],

            'items.*.product_id' => [
                'required_with:items',
                TenantRule::exists(
                    'products'
                ),
            ],

            'items.*.warehouse_id' => [
                'required_with:items',
                TenantRule::exists(
                    'warehouses'
                ),
            ],

            'items.*.quantity' => [
                'required_with:items',
                'numeric',
                'gt:0',
            ],

            'items.*.unit_cost' => [
                'required_with:items',
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
                    PurchaseReturnCondition::class
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
