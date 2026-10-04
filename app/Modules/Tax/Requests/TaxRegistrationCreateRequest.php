<?php

namespace App\Modules\Tax\Requests;

use Illuminate\Validation\Rule;

use App\Core\Requests\BaseRequest;
use App\Modules\Tax\Enums\TaxRegistrationTypeEnum;

class TaxRegistrationCreateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'registrable_type' => [
                'required',
                'string',
                'max:100',
            ],

            'registrable_id' => [
                'required',
                'integer',
                'min:1',
            ],

            'registration_type' => [
                'required',
                'string',
                Rule::enum(TaxRegistrationTypeEnum::class),
            ],

            'registration_number' => [
                'required',
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