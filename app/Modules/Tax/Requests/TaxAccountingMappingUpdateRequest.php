<?php

namespace App\Modules\Tax\Requests;

use Illuminate\Validation\Rule;

use App\Core\Requests\BaseRequest;
use App\Core\Validation\TenantRule;
use App\Modules\Tax\Enums\TaxDirectionEnum;

class TaxAccountingMappingUpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'tax_component_id' => [
                'sometimes',
                'integer',
                TenantRule::exists('tax_components'),
            ],

            'account_id' => [
                'sometimes',
                'integer',
                TenantRule::exists('chart_of_accounts'),
            ],

            'direction' => [
                'sometimes',
                'string',
                Rule::enum(TaxDirectionEnum::class),
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
        ];
    }
}