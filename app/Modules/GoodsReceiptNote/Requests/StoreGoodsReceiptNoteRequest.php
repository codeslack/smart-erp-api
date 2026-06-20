<?php

namespace App\Modules\GoodsReceiptNote\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Core\Validation\TenantRule;

class StoreGoodsReceiptNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'purchase_order_id' => [
                'required',
                'integer',
                TenantRule::exists(
                    'purchase_orders'
                ),
            ],

            'received_date' => [
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
                'integer',
                TenantRule::exists(
                    'products'
                ),
            ],

            'items.*.warehouse_id' => [
                'required',
                'integer',
                TenantRule::exists(
                    'warehouses'
                ),
            ],

            'items.*.received_quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],
        ];
    }
}
