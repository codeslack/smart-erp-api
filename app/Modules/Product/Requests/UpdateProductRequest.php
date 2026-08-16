<?php

namespace App\Modules\Product\Requests;

use Illuminate\Validation\Validator;

use App\Core\Validation\TenantRule;

class UpdateProductRequest extends BaseProductRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $product =
            $this->route('product');

        return array_merge(
            $this->commonRules(true),

            [

                'sku' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:100',

                    TenantRule::uniqueIgnore(
                        'products',
                        'sku',
                        $product->id
                    ),
                ],

                'barcode' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:100',

                    TenantRule::uniqueIgnore(
                        'products',
                        'barcode',
                        $product->id
                    ),
                ],

                /*
                |--------------------------------------------------------------------------
                | Variants
                |--------------------------------------------------------------------------
                */

                'variants' => [
                    'sometimes',
                    'array',
                ],

                'variants.*.uuid' => [
                    'sometimes',
                    'uuid',
                ],

                'variants.*.name' => [
                    'sometimes',
                    'string',
                    'max:255',
                ],

                'variants.*.sku' => [
                    'sometimes',
                    'string',
                    'max:100',
                ],

                'variants.*.barcode' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:100',
                ],

                'variants.*.purchase_price' => [
                    'sometimes',
                    'numeric',
                    'min:0',
                ],

                'variants.*.selling_price' => [
                    'sometimes',
                    'numeric',
                    'min:0',
                ],

                'variants.*.is_active' => [
                    'sometimes',
                    'boolean',
                ],

                'variants.*.attributes' => [
                    'sometimes',
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

    public function withValidator(
        Validator $validator
    ): void {

        parent::withValidator(
            $validator
        );

        $validator->after(
            function (
                Validator $validator
            ) {

                $product =
                    $this->route('product');

                $hasVariants =
                    $this->has('has_variants')
                        ? $this->boolean('has_variants')
                        : $product->has_variants;

                if (
                    ! $hasVariants
                    && filled($this->variants)
                ) {
                    $validator->errors()->add(
                        'variants',
                        'Variants are only allowed when has_variants is enabled.'
                    );
                }
            }
        );
    }
}