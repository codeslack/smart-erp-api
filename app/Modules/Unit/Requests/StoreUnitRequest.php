<?php

namespace App\Modules\Unit\Requests;

use App\Core\Validation\TenantRule;

use App\Core\Requests\BaseRequest;

class StoreUnitRequest extends BaseRequest
{
    public function rules(): array
    {
        return [

            'name' => [
                'required',
                'string',
                'max:255',

                TenantRule::unique(
                    'units',
                    'name'
                ),
            ],

            'short_name' => [
                'required',
                'string',
                'max:50',
                
                TenantRule::unique(
                    'units',
                    'short_name'
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