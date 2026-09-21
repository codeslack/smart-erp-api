<?php

namespace App\Modules\OpeningStock\Requests;

use Illuminate\Foundation\Http\FormRequest;

use App\Core\Validation\TenantRule;

class StoreOpeningStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => [
                'required',
                TenantRule::exists(
                    'warehouses'
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

            'sources' => [
                'required',
                'array',
                'min:1',
            ],

            'sources.*' => [
                'required',
                'array',
            ],

            'sources.*.supplier_id' => [
                'nullable',
                TenantRule::exists(
                    'suppliers'
                ),
            ],

            'sources.*.bill_no' => [
                'required',
                'string',
                'max:100',
            ],

            'sources.*.bill_date' => [
                'required',
                'date',
            ],

            'sources.*.remarks' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'sources.*.items' => [
                'required',
                'array',
                'min:1',
            ],

            'sources.*.items.*' => [
                'required',
                'array',
            ],

            'sources.*.items.*.product_id' => [
                'required',
                TenantRule::exists(
                    'products'
                ),
            ],

            'sources.*.items.*.product_variant_id' => [
                'nullable',
                TenantRule::exists(
                    'product_variants'
                ),
            ],

            'sources.*.items.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'sources.*.items.*.unit_cost' => [
                'required',
                'numeric',
                'gte:0',
            ],

            /*
            |------------------------------------------------------------------
            | Batch Information
            |------------------------------------------------------------------
            */

            'sources.*.items.*.batch_no' => [
                'nullable',
                'string',
                'max:100',
            ],

            'sources.*.items.*.manufacturing_date' => [
                'nullable',
                'date',
            ],

            'sources.*.items.*.expiry_date' => [
                'nullable',
                'date',
            ],

            /*
            |------------------------------------------------------------------
            | Serial Information
            |------------------------------------------------------------------
            */

            'sources.*.items.*.serial_number' => [
                'nullable',
                'string',
                'max:150',
            ],

            'sources.*.items.*.imei_number' => [
                'nullable',
                'string',
                'max:150',
            ],

            'sources.*.items.*.warranty_expiry' => [
                'nullable',
                'date',
            ],

            'sources.*.items.*.remarks' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }
}
