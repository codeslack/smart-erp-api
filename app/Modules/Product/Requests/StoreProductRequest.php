<?php

namespace App\Modules\Product\Requests;

use App\Core\Validation\TenantRule;

class StoreProductRequest extends BaseProductRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge(
            $this->commonRules(),

            [

                'sku' => [
                    'nullable',
                    'string',
                    'max:100',

                    TenantRule::unique(
                        'products',
                        'sku'
                    ),
                ],

                'barcode' => [
                    'nullable',
                    'string',
                    'max:100',

                    TenantRule::unique(
                        'products',
                        'barcode'
                    ),
                ],

                /*
                |--------------------------------------------------------------------------
                | Variants
                |--------------------------------------------------------------------------
                */

                'variants' => [
                    'nullable',
                    'array',
                ],

                'variants.*.name' => [
                    'required_with:variants',
                    'string',
                    'max:255',
                ],

                'variants.*.sku' => [
                    'required_with:variants',
                    'string',
                    'max:100',
                ],

                'variants.*.barcode' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'variants.*.purchase_price' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'variants.*.selling_price' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'variants.*.attributes' => [
                    'nullable',
                    'array',
                ],

                'variants.*.attributes.*.attribute_name' => [
                    'required_with:variants.*.attributes',
                    'string',
                    'max:100',
                ],

                'variants.*.attributes.*.attribute_value' => [
                    'required_with:variants.*.attributes',
                    'string',
                    'max:255',
                ],
            ]
        );
    }
}