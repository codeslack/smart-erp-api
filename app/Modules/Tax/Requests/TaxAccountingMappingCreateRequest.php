<?php

namespace App\Modules\Tax\Requests;

use Illuminate\Validation\Rule;

use App\Core\Requests\BaseRequest;
use App\Core\Validation\TenantRule;
use App\Modules\Tax\Enums\TaxDirectionEnum;

class TaxAccountingMappingCreateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'tax_component_id' => [
                'required',
                'integer',
                TenantRule::exists('tax_components'),
            ],

            'account_id' => [
                'required',
                'integer',
                TenantRule::exists('chart_of_accounts'),
            ],

            'direction' => [
                'required',
                'string',
                Rule::enum(TaxDirectionEnum::class),
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
        ];
    }
}