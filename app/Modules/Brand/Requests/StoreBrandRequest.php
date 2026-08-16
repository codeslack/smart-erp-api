<?php

namespace App\Modules\Brand\Requests;

use App\Core\Validation\TenantRule;

use App\Core\Requests\BaseRequest;

class StoreBrandRequest extends BaseRequest
{
    public function rules(): array
    {
        return [

            /**
             * The brand name, which must be unique within the tenant's context.
             * @example Acme Corporation
             */
            'name' => [
                'required',
                'string',
                'max:255',

                TenantRule::unique(
                    'brands',
                    'name'
                ),
            ],

            /**
             * A brief description of the brand.
             * @example Acme Corporation
             */
            'description' => [
                'nullable',
                'string',
            ],

            /**
             * boolean flag indicating whether the brand is active or inactive.
             * @example true
             */
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
