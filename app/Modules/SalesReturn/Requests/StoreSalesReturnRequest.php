<?php

namespace App\Modules\SalesReturn\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Core\Validation\TenantRule;

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
        ];
    }
}
