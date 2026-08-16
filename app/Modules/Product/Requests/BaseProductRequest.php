<?php

namespace App\Modules\Product\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

use App\Core\Validation\TenantRule;

use App\Modules\Product\Enums\ProductTypeEnum;
use App\Modules\Product\Enums\InventoryTrackingTypeEnum;

abstract class BaseProductRequest extends FormRequest
{
    protected function commonRules(
        bool $updating = false
    ): array {

        $required = $updating
            ? 'sometimes'
            : 'required';

        return [

            /*
            |--------------------------------------------------------------------------
            | Relations
            |--------------------------------------------------------------------------
            */

            'category_uuid' => [
                'nullable',
                'uuid',
            ],

            'brand_uuid' => [
                'nullable',
                'uuid',
            ],

            'unit_uuid' => [
                'nullable',
                'uuid',
            ],

            'category_id' => [
                'nullable',
                'integer',
                TenantRule::exists('categories'),
            ],

            'brand_id' => [
                'nullable',
                'integer',
                TenantRule::exists('brands'),
            ],

            'unit_id' => [
                'nullable',
                'integer',
                TenantRule::exists('units'),
            ],

            /*
            |--------------------------------------------------------------------------
            | Product
            |--------------------------------------------------------------------------
            */

            'name' => [
                $required,
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'product_type' => [
                $required,
                Rule::in(
                    ProductTypeEnum::values()
                ),
            ],

            'inventory_tracking_type' => [
                $required,
                Rule::in(
                    InventoryTrackingTypeEnum::values()
                ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Flags
            |--------------------------------------------------------------------------
            */

            'has_expiry' => [
                'sometimes',
                'boolean',
            ],

            'has_warranty' => [
                'sometimes',
                'boolean',
            ],

            'has_variants' => [
                'sometimes',
                'boolean',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
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
            | Stock
            |--------------------------------------------------------------------------
            */

            'minimum_stock' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'maximum_stock' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'reorder_level' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'critical_level' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            /*
            |--------------------------------------------------------------------------
            | Manufacturer
            |--------------------------------------------------------------------------
            */

            'manufacturer' => [
                'nullable',
                'string',
                'max:255',
            ],

            'model_number' => [
                'nullable',
                'string',
                'max:255',
            ],

            'part_number' => [
                'nullable',
                'string',
                'max:255',
            ],

            /*
            |--------------------------------------------------------------------------
            | Dimensions
            |--------------------------------------------------------------------------
            */

            'weight' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'length' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'width' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'height' => [
                'nullable',
                'numeric',
                'min:0',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $trackingType =
            $this->inventory_tracking_type;

        $hasVariants =
            $this->boolean(
                'has_variants'
            );

        $this->merge([

            'category_id' => resolveModelId(
                \App\Modules\Category\Models\Category::class,
                $this->category_uuid
            ),

            'brand_id' => resolveModelId(
                \App\Modules\Brand\Models\Brand::class,
                $this->brand_uuid
            ),

            'unit_id' => resolveModelId(
                \App\Modules\Unit\Models\Unit::class,
                $this->unit_uuid
            ),

            'purchase_price' => $hasVariants
                ? 0
                : ($this->purchase_price ?? 0),

            'selling_price' => $hasVariants
                ? 0
                : ($this->selling_price ?? 0),

            'minimum_stock' =>
                $this->minimum_stock ?? 0,

            'maximum_stock' =>
                $this->maximum_stock ?? 0,

            'reorder_level' =>
                $this->reorder_level ?? 0,

            'critical_level' =>
                $this->critical_level ?? 0,

            'has_expiry' =>
                $trackingType === 'BATCH'
                    ? $this->boolean('has_expiry')
                    : false,

            'has_warranty' =>
                $trackingType === 'SERIAL'
                    ? $this->boolean('has_warranty')
                    : false,

            'has_variants' =>
                $hasVariants,

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

                if (
                    $this->inventory_tracking_type === 'SERIAL'
                    && $this->has_expiry
                ) {
                    $validator->errors()->add(
                        'has_expiry',
                        'Serial tracked products cannot use expiry tracking.'
                    );
                }

                if (
                    $this->inventory_tracking_type === 'BATCH'
                    && $this->has_warranty
                ) {
                    $validator->errors()->add(
                        'has_warranty',
                        'Batch tracked products cannot use warranty tracking.'
                    );
                }
            }
        );
    }
}