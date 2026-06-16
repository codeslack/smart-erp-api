<?php

namespace App\Modules\Sales\Requests;

use App\Core\Validation\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'customer_id' => [
                'required',
                TenantRule::exists(
                    'customers'
                ),
            ],

            'sale_date' => [
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

            'items.*.unit_price' => [
                'required',
                'numeric',
                'gt:0',
            ],
        ];
    }
}
