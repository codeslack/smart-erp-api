<?php

namespace App\Modules\ProductUnit\Requests;

use App\Core\Requests\BaseRequest;
use App\Core\Validation\TenantRule;

class StoreProductUnitRequest extends BaseRequest
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
                'integer',
                TenantRule::exists('products'),
            ],

            'unit_id' => [
                'required',
                'integer',
                TenantRule::exists('units'),
            ],

            'conversion_factor' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'is_purchase_unit' => [
                'sometimes',
                'boolean',
            ],

            'is_sales_unit' => [
                'sometimes',
                'boolean',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}