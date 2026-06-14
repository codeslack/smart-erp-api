<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpeningStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'product_id' => [
                'required',
                'integer'
            ],

            'warehouse_id' => [
                'required',
                'integer'
            ],

            'quantity' => [
                'required',
                'numeric',
                'gt:0'
            ],

            'remarks' => [
                'nullable',
                'string'
            ],
        ];
    }
}