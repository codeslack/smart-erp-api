<?php

namespace App\Modules\Product\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use App\Modules\Product\Enums\ProductTypeEnum;
use App\Modules\Product\Enums\ProductStatusEnum;
use App\Modules\Product\Enums\InventoryTrackingTypeEnum;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // $data = $this->applyDefaults($data);
        return [

            'category_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
            ],

            'brand_id' => [
                'nullable',
                'integer',
                'exists:brands,id',
            ],

            'unit_id' => [
                'nullable',
                'integer',
                'exists:units,id',
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'barcode' => [
                'nullable',
                'string',
                'max:255',
            ],

            'product_type' => [
                'required',
                Rule::enum(ProductTypeEnum::class),
            ],

            'inventory_tracking_type' => [
                'nullable',
                Rule::enum(
                    InventoryTrackingTypeEnum::class
                ),
            ],

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

            'description' => [
                'nullable',
                'string',
            ],

            'status' => [
                'nullable',
                Rule::enum(ProductStatusEnum::class),
            ],
        ];
    }
}