<?php

namespace App\Modules\Purchase\Requests;

use App\Core\Validation\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => [
                'required',
                TenantRule::exists('suppliers'),
            ],

            'purchase_date' => [
                'required',
                'date',
            ],

            'notes' => [
                'nullable',
                'string',
            ],

            'items' => [
                'required',
                'array',
                'min:1'
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

            'items.*.unit_cost' => [
                'required',
                'numeric',
                'gte:0',
            ],
        ];
    }
}
