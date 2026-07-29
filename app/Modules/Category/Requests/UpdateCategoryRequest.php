<?php

namespace App\Modules\Category\Requests;

use App\Core\Validation\TenantRule;

use App\Core\Requests\BaseRequest;

class UpdateCategoryRequest extends BaseRequest
{
    public function rules(): array
    {
        $category = $this->route('category');

        return [

            'name' => [
                'sometimes',
                'string',
                'max:255',

                TenantRule::uniqueIgnore(
                    'categories',
                    'name',
                    $category->id
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
