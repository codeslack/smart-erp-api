<?php

namespace App\Modules\Tax\Requests;

use App\Core\Requests\BaseRequest;
use App\Core\Validation\TenantRule;

class TaxApplicabilityRuleUpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'tax_group_id' => [
                'sometimes',
                'integer',
                TenantRule::exists('tax_groups'),
            ],

            'source_country_code' => [
                'sometimes',
                'nullable',
                'string',
                'max:10',
            ],

            'source_state_code' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
            ],

            'destination_country_code' => [
                'sometimes',
                'nullable',
                'string',
                'max:10',
            ],

            'destination_state_code' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
            ],

            'place_of_supply_state_code' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
            ],

            'source_registration_required' => [
                'sometimes',
                'nullable',
                'boolean',
            ],

            'destination_registration_required' => [
                'sometimes',
                'nullable',
                'boolean',
            ],

            'priority' => [
                'sometimes',
                'integer',
                'min:1',
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
