<?php

namespace App\Modules\Product\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

use App\Core\Validation\TenantRule;

use App\Modules\Product\Enums\ProductTypeEnum;
use App\Modules\Product\Enums\InventoryTrackingTypeEnum;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [

            /*
            |--------------------------------------------------------------------------
            | Relations
            |--------------------------------------------------------------------------
            */

            /**
             * The ID of the product category.
             * @example 1
             */
            'category_id' => [
                'nullable',
                'integer',
                TenantRule::exists(
                    'categories'
                ),
            ],

            /**
             * The ID of the product brand.
             * @example 1
             */
            'brand_id' => [
                'nullable',
                'integer',
                TenantRule::exists(
                    'brands'
                ),
            ],

            /**
             * The ID of the base unit of measurement.
             * @example 1
             */
            'unit_id' => [
                'nullable',
                'integer',
                TenantRule::exists(
                    'units'
                ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Identity
            |--------------------------------------------------------------------------
            */

            /**
             * Stock Keeping Unit unique identifier.
             * @example PARA-500
             */
            'sku' => [
                'nullable',
                'string',
                'max:100',

                TenantRule::uniqueIgnore(
                    'products',
                    'sku',
                    $product->id
                ),
            ],

            /**
             * Universal product barcode identifier string.
             * @example 8901234567890
             */
            'barcode' => [
                'nullable',
                'string',
                'max:100',

                TenantRule::uniqueIgnore(
                    'products',
                    'barcode',
                    $product->id
                ),
            ],

            /**
             * Commercial name of the item.
             * @example Paracetamol 500mg Tablet
             */
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            /**
             * Extended item description details.
             * @example Paracetamol 500mg oral tablet
             */
            'description' => [
                'nullable',
                'string',
            ],

            /*
            |--------------------------------------------------------------------------
            | Classification
            |--------------------------------------------------------------------------
            */

            /**
             * General product classification tier.
             * @example MEDICINE
             */
            'product_type' => [
                'required',
                Rule::enum(
                    ProductTypeEnum::class
                ),
            ],

            /**
             * Chosen inventory valuation mapping approach.
             * @example BATCH
             */
            'inventory_tracking_type' => [
                'required',
                Rule::enum(
                    InventoryTrackingTypeEnum::class
                ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Inventory Behaviour
            |--------------------------------------------------------------------------
            */

            /**
             * Flag indicating whether items contain shelf expiration properties.
             * @example true
             */
            'has_expiry' => [
                'boolean',
            ],

            /**
             * Flag indicating whether item transactions carry consumer warranty coverage.
             * @example false
             */
            'has_warranty' => [
                'boolean',
            ],

            /**
             * Flag defining whether the item splits into sub-variant layers.
             * @example false
             */
            'has_variants' => [
                'boolean',
            ],

            /*
            |--------------------------------------------------------------------------
            | Pricing
            |--------------------------------------------------------------------------
            */

            /**
             * Cost valuation profile spent acquiring the unit.
             * @example 5.00
             */
            'purchase_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            /**
             * Base target consumer sales catalog pricing layout.
             * @example 8.00
             */
            'selling_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            /*
            |--------------------------------------------------------------------------
            | Stock Control
            |--------------------------------------------------------------------------
            */

            /**
             * Absolute storage room floor constraint quantity.
             * @example 100
             */
            'minimum_stock' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            /**
             * Target distribution facility upper limit allocation.
             * @example 10000
             */
            'maximum_stock' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            /**
             * Trigger point value setting off automated restock generation cycles.
             * @example 500
             */
            'reorder_level' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            /**
             * Urgent stock out threshold trigger boundary line marker.
             * @example 100
             */
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

            /**
             * Production processing firm identity label.
             * @example ABC Pharma Ltd
             */
            'manufacturer' => [
                'nullable',
                'string',
                'max:255',
            ],

            /**
             * Assigned manufacturer design catalog structural text reference.
             * @example null
             */
            'model_number' => [
                'nullable',
                'string',
                'max:255',
            ],

            /**
             * Mechanical component segment engineering standard reference index.
             * @example null
             */
            'part_number' => [
                'nullable',
                'string',
                'max:255',
            ],

            /*
            |--------------------------------------------------------------------------
            | Physical Properties
            |--------------------------------------------------------------------------
            */

            /**
             * Net gravity mass weight payload volume measured in kilograms.
             * @example 0.020
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

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            /**
             * Operational availability status flag.
             * @example true
             */
            'is_active' => [
                'boolean',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $trackingType =
            $this->inventory_tracking_type;

        $this->merge([

            'has_expiry' =>
            $trackingType === 'BATCH'
                ? $this->boolean('has_expiry')
                : false,

            'has_warranty' =>
            $trackingType === 'SERIAL'
                ? $this->boolean('has_warranty')
                : false,

            'has_variants' =>
            $this->boolean('has_variants'),

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

        $validator->after(function ( Validator $validator) {

            $product = $this->route('product');

            $hasVariants =
                $this->has('has_variants')
                ? $this->boolean('has_variants')
                : $product->has_variants;

            if (
                ! $hasVariants &&
                filled($this->variants)
            ) {

                $validator->errors()->add(
                    'variants',
                    'Variants are only allowed when has_variants is enabled.'
                );
            }
        });
    }
}
