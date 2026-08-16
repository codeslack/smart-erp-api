<?php

namespace App\Modules\OpeningStock\Requests;

use Illuminate\Foundation\Http\FormRequest;

use App\Core\Validation\TenantRule;

class StoreOpeningStockRequest
    extends FormRequest
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
            | Header
            |--------------------------------------------------------------------------
            */

            'warehouse_id' => [
                'required',
                TenantRule::exists(
                    'warehouses'
                ),
            ],

            'supplier_id' => [
                'nullable',
                TenantRule::exists(
                    'suppliers'
                ),
            ],

            'opening_date' => [
                'required',
                'date',
            ],

            'remarks' => [
                'nullable',
                'string',
                'max:1000',
            ],

            /*
            |--------------------------------------------------------------------------
            | Items
            |--------------------------------------------------------------------------
            */

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.product_id' => [
                'required',
                TenantRule::exists(
                    'products'
                ),
            ],

            'items.*.product_variant_id' => [
                'nullable',
                TenantRule::exists(
                    'product_variants'
                ),
            ],

            'items.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'items.*.unit_cost' => [
                'required',
                'numeric',
                'gte:0',
            ],

            'items.*.remarks' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }
}