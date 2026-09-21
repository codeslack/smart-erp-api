<?php
// :::writing{variant="document" id="28416" title="UpdateOpeningStockRequest"}

namespace App\Modules\OpeningStock\Requests;

use Illuminate\Foundation\Http\FormRequest;

use App\Core\Validation\TenantRule;

class UpdateOpeningStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => [
                'sometimes',
                'required',
                TenantRule::exists(
                    'warehouses'
                ),
            ],

            'opening_date' => [
                'sometimes',
                'required',
                'date',
            ],

            'remarks' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],

            'sources' => [
                'sometimes',
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

            'sources.*.items.*.product_batch_id' => [
                'nullable',
                TenantRule::exists(
                    'product_batches'
                ),
            ],

            'sources.*.items.*.product_serial_id' => [
                'nullable',
                TenantRule::exists(
                    'product_serials'
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

            'sources.*.items.*.remarks' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }
}