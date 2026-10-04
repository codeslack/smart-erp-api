<?php

namespace App\Modules\Tax\Requests;

use Illuminate\Validation\Rule;

use App\Core\Requests\BaseRequest;

use App\Core\Validation\TenantRule;

use App\Modules\Tax\Enums\TaxDirectionEnum;
use App\Modules\Tax\Enums\TaxTreatmentEnum;
use App\Modules\Tax\Enums\TaxUsageEnum;

class TaxGroupUpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'tax_id' => [
                'sometimes',
                'integer',
                TenantRule::exists('taxes'),
            ],

            'name' => [
                'sometimes',
                'string',
                'max:150',
            ],

            'usage' => [
                'sometimes',
                'string',
                Rule::enum(TaxUsageEnum::class),
            ],

            'direction' => [
                'sometimes',
                'string',
                Rule::enum(TaxDirectionEnum::class),
            ],

            'treatment' => [
                'sometimes',
                'string',
                Rule::enum(TaxTreatmentEnum::class),
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