<?php

namespace App\Modules\Tax\Requests;

use Illuminate\Validation\Rule;

use App\Core\Requests\BaseRequest;
use App\Modules\Tax\Enums\TaxRegistrationTypeEnum;
use App\Modules\Tax\Domain\TaxRegistrationRules;

class TaxRegistrationUpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'registrable_type' => [
                'sometimes',
                'string',
                Rule::in(array_keys(TaxRegistrationRules::registrableMorphMap())),
            ],

            'registrable_id' => [
                'sometimes',
                'integer',
                'min:1',
            ],

            'registration_type' => [
                'sometimes',
                'string',
                Rule::enum(TaxRegistrationTypeEnum::class),
            ],

            'registration_number' => [
                'sometimes',
                'string',
                'max:100',
            ],

            'country_code' => [
                'sometimes',
                'nullable',
                'string',
                'max:10',
            ],

            'state_code' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
            ],

            'is_primary' => [
                'sometimes',
                'boolean',
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