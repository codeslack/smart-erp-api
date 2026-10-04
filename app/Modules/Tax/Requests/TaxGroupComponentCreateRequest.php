<?php

namespace App\Modules\Tax\Requests;

use Illuminate\Validation\Rule;

use App\Core\Requests\BaseRequest;

use App\Core\Validation\TenantRule;

use App\Modules\Tax\Enums\TaxCalculationMethodEnum;

class TaxGroupComponentCreateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'tax_group_id' => [
                'required',
                'integer',
                TenantRule::exists('tax_groups'),
            ],

            'tax_component_id' => [
                'required',
                'integer',
                TenantRule::exists('tax_components'),
            ],

            'rate' => [
                'required',
                'numeric',
                'min:0',
            ],

            'calculation_method' => [
                'required',
                'string',
                Rule::enum(TaxCalculationMethodEnum::class),
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],

            'effective_from' => [
                'required',
                'date',
            ],

            'effective_to' => [
                'nullable',
                'date',
                'after_or_equal:effective_from',
            ],

            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],
        ];
    }
}