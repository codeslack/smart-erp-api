<?php

namespace App\Modules\PurchaseReturn\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Core\Validation\TenantRule;

class StorePurchaseReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'purchase_id' => [
                'required',
                TenantRule::exists(
                    'purchases'
                ),
            ],

            'supplier_id' => [
                'required',
                TenantRule::exists(
                    'suppliers'
                ),
            ],

            'return_date' => [
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
                'min:1',
            ],

            'items.*.product_id' => [
                'required',
                TenantRule::exists(
                    'products'
                ),
            ],

            'items.*.warehouse_id' => [
                'required',
                TenantRule::exists(
                    'warehouses'
                ),
            ],

            'items.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'items.*.unit_cost' => [
                'required',
                'numeric',
                'gt:0',
            ],
        ];
    }
}