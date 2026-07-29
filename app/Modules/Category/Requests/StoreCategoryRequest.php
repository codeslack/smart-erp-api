<?php

namespace App\Modules\Category\Requests;

use App\Core\Validation\TenantRule;

use App\Core\Requests\BaseRequest;

class StoreCategoryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [

            'name' => [
                'required',
                'string',
                'max:255',

                TenantRule::unique(
                    'categories',
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
