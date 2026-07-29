<?php

namespace App\Modules\Brand\Requests;

use App\Core\Validation\TenantRule;

use App\Core\Requests\BaseRequest;

class StoreBrandRequest extends BaseRequest
{
    public function rules(): array
    {
        return [

            'name' => [
                'required',
                'string',
                'max:255',

                TenantRule::unique(
                    'brands',
                    'name'
                ),
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
