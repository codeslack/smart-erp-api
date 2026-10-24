<?php

namespace App\Modules\ProductUnit\Requests;

use App\Core\Requests\BaseRequest;

class UpdateProductUnitRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'conversion_factor' => [
                'sometimes',
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