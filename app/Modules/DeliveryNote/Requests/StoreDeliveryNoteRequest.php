<?php

namespace App\Modules\DeliveryNote\Requests;

use App\Core\Validation\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDeliveryNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'sales_order_id' => [
                'required',
                TenantRule::exists(
                    'sales_orders'
                ),
            ],

            'delivery_date' => [
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

            'items.*.delivered_quantity' => [
                'required',
                'numeric',
                'min:0.0001',
            ],
        ];
    }
}
