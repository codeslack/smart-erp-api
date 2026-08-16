<?php

namespace App\Modules\Category\Requests;

use App\Core\Validation\TenantRule;

use App\Core\Requests\BaseRequest;

class StoreCategoryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [

            /**
             * The name of the category, which must be unique within the tenant's context.
             * @example Medicine
             */
            'name' => [
                'required',
                'string',
                'max:255',

                TenantRule::unique(
                    'categories',
                    'name'
                ),
            ],

            /**
             * A brief description of the category.
             * @example This category includes all types of medicines and pharmaceuticals.
             */
            'description' => [
                'nullable',
                'string',
            ],

            /**
             * boolean flag indicating whether the category is active or inactive.
             * @example true
             */
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
