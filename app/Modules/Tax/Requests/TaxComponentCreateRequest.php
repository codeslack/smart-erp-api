<?php

namespace App\Modules\Tax\Requests;

use Illuminate\Validation\Rule;

use App\Core\Requests\BaseRequest;

use App\Modules\Tax\Enums\TaxComponentTypeEnum;

class TaxComponentCreateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'component_type' => [
                'required',
                'string',
                Rule::enum(TaxComponentTypeEnum::class),
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}