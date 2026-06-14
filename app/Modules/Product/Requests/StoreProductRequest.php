<?php

namespace App\Modules\Product\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'category_id' => ['required', 'integer'],
            'unit_id' => ['required', 'integer'],

            'brand_id' => ['nullable', 'integer'],

            'name' => ['required', 'string', 'max:255'],

            'sku' => [
                'required', 
                'string', 'max:100',
                'unique:products,sku'
            ],

            'barcode' => ['nullable', 'string', 'max:100'],

            'purchase_price' => [
                'required',
                'numeric',
                'min:0'
            ],

            'sale_price' => [
                'required',
                'numeric',
                'min:0'
            ],

            'minimum_stock' => [
                'nullable',
                'numeric',
                'min:0'
            ],

            'description' => [
                'nullable',
                'string'
            ],

            'is_active' => [
                'boolean'
            ],
        ];
    }
}