<?php

namespace App\Modules\Tax\Requests;

use Illuminate\Validation\Rule;

use App\Core\Requests\BaseRequest;

use App\Modules\Tax\Enums\TaxTypeEnum;

class TaxUpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'string',
                'max:150',
            ],

            'tax_type' => [
                'sometimes',
                'string',
                Rule::enum(TaxTypeEnum::class),
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
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