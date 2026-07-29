<?php

namespace App\Modules\Brand\Requests;

use App\Core\Validation\TenantRule;

use App\Core\Requests\BaseRequest;

class UpdateBrandRequest extends BaseRequest
{
    public function rules(): array
    {
        $brand = $this->route('brand');

        return [

            'name' => [
                'sometimes',
                'string',
                'max:255',

                TenantRule::uniqueIgnore(
                    'brands',
                    'name',
                    $brand->id
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
