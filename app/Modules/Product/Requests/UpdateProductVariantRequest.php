<?php

namespace App\Modules\Product\Requests;

use Illuminate\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

use App\Core\Validation\TenantRule;

class UpdateProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $variant = $this->route(
            'product_variant'
        );

        if (! $variant) {
            throw new \RuntimeException(
                'Product variant route binding failed.'
            );
        }

        return [

            /*
            |--------------------------------------------------------------------------
            | Product
            |--------------------------------------------------------------------------
            */

            'product_id' => [
                'sometimes',
                'integer',

                TenantRule::exists(
                    'products'
                ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Identity
            |--------------------------------------------------------------------------
            */

            'sku' => [
                'sometimes',
                'string',
                'max:100',

                TenantRule::uniqueIgnore(
                    'product_variants',
                    'sku',
                    $variant->id
                ),
            ],

            'barcode' => [
                'nullable',
                'string',
                'max:100',

                TenantRule::uniqueIgnore(
                    'product_variants',
                    'barcode',
                    $variant->id
                ),
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            /*
            |--------------------------------------------------------------------------
            | Pricing
            |--------------------------------------------------------------------------
            */

            'purchase_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'selling_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            'is_active' => [
                'boolean',
            ],

            /*
            |--------------------------------------------------------------------------
            | Attributes
            |--------------------------------------------------------------------------
            */

            'attributes' => [
                'nullable',
                'array',
            ],

            'attributes.*.attribute_name' => [
                'required_with:attributes',
                'string',
                'max:100',
            ],

            'attributes.*.attribute_value' => [
                'required_with:attributes',
                'string',
                'max:255',
            ],
        ];
    }

    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(
            function (
                Validator $validator
            ) {

                $attributes = collect(
                    $this->input(
                        'attributes',
                        []
                    )
                );

                $duplicates = $attributes
                    ->pluck('attribute_name')
                    ->filter()
                    ->duplicates();

                if (
                    $duplicates->isNotEmpty()
                ) {

                    $validator->errors()->add(
                        'attributes',
                        'Duplicate attribute names are not allowed.'
                    );
                }
            }
        );
    }
}
