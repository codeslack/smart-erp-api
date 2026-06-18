<?php

namespace App\Modules\StockTransfer\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Core\Validation\TenantRule;

class StoreStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'from_warehouse_id' => [
                'required',
                TenantRule::exists('warehouses'),
            ],

            'to_warehouse_id' => [
                'required',
                'different:from_warehouse_id',
                TenantRule::exists('warehouses'),
            ],

            'transfer_date' => [
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

            'items.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],
        ];
    }
}
