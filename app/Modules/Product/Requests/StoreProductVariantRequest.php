<?php

namespace App\Modules\Product\Requests;

use Illuminate\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

use App\Core\Validation\TenantRule;

class StoreProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            /*
            |--------------------------------------------------------------------------
            | Product
            |--------------------------------------------------------------------------
            */

            /**
             * The UUID of the product to which this variant belongs.
             * @example 123e4567-e89b-12d3-a456-426614174000
             */
            'product_uuid' => [
                'required',
                'string',
                'uuid',
            ],
            
            'product_id' => [
                'required',
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
                'required',
                'string',
                'max:100',

                TenantRule::unique(
                    'product_variants',
                    'sku'
                ),
            ],

            'barcode' => [
                'nullable',
                'string',
                'max:100',

                TenantRule::unique(
                    'product_variants',
                    'barcode'
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

    protected function prepareForValidation(): void
    {

        $this->merge([

            'product_id' => resolveModelId(
                \App\Modules\Product\Models\Product::class,
                $this->product_uuid
            ),

            'purchase_price' => $this->purchase_price ?? 0,

            'selling_price'  => $this->selling_price ?? 0,

            'is_active' =>
                $this->boolean(
                    'is_active',
                    true
                ),
        ]);
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
