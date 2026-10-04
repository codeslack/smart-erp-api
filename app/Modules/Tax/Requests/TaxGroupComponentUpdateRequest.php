<?php

namespace App\Modules\Tax\Requests;

use Illuminate\Validation\Rule;

use App\Core\Requests\BaseRequest;
use App\Core\Validation\TenantRule;
use App\Modules\Tax\Enums\TaxCalculationMethodEnum;

class TaxGroupComponentUpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'tax_group_id' => [
                'sometimes',
                'integer',
                TenantRule::exists('tax_groups'),
            ],

            'tax_component_id' => [
                'sometimes',
                'integer',
                TenantRule::exists('tax_components'),
            ],

            'rate' => [
                'sometimes',
                'numeric',
                'min:0',
            ],

            'calculation_method' => [
                'sometimes',
                'string',
                Rule::enum(TaxCalculationMethodEnum::class),
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],

            'effective_from' => [
                'sometimes',
                'date',
            ],

            'effective_to' => [
                'sometimes',
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