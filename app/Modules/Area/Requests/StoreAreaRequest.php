<?php

namespace App\Modules\Area\Requests;

use App\Core\Validation\TenantRule;

use App\Core\Requests\BaseRequest;

class StoreAreaRequest extends BaseRequest
{
    public function rules(): array
    {
        return [

            'name' => [
                'required',
                'string',
                'max:255',

                TenantRule::unique(
                    'areas',
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
