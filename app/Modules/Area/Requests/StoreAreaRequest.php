<?php

namespace App\Modules\Area\Requests;

use App\Core\Validation\TenantRule;

use App\Core\Requests\BaseRequest;

class StoreAreaRequest extends BaseRequest
{
    public function rules(): array
    {
        return [

            /**
             * The name of the area, which must be unique within the tenant's context.
             * @example North Zone
             */
            'name' => [
                'required',
                'string',
                'max:255',

                TenantRule::unique(
                    'areas',
                    'name'
                ),
            ],

            /**
             * A brief description of the area.
             * @example This area covers the northern region of the city.
             */
            'description' => [
                'nullable',
                'string',
            ],

            /**
             * boolean flag indicating whether the area is active or inactive.
             * @example true
             */
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
