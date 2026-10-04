<?php

namespace App\Modules\Tax\Requests;

use Illuminate\Validation\Rule;

use App\Core\Requests\BaseRequest;

use App\Core\Validation\TenantRule;

use App\Modules\Tax\Enums\TaxDirectionEnum;
use App\Modules\Tax\Enums\TaxTreatmentEnum;
use App\Modules\Tax\Enums\TaxUsageEnum;

class TaxGroupCreateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'tax_id' => [
                'required',
                'integer',
                TenantRule::exists('taxes'),
            ],

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'usage' => [
                'required',
                'string',
                Rule::enum(TaxUsageEnum::class),
            ],

            'direction' => [
                'required',
                'string',
                Rule::enum(TaxDirectionEnum::class),
            ],

            'treatment' => [
                'required',
                'string',
                Rule::enum(TaxTreatmentEnum::class),
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