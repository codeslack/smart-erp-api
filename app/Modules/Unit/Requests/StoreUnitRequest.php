<?php

namespace App\Modules\Unit\Requests;

use App\Core\Validation\TenantRule;

use App\Core\Requests\BaseRequest;

class StoreUnitRequest extends BaseRequest
{
    public function rules(): array
    {
        return [

            /**
             * The unit name, which must be unique within the tenant's context.
             * @example Kilogram
             */
            'name' => [
                'required',
                'string',
                'max:255',

                TenantRule::unique(
                    'units',
                    'name'
                ),
            ],

            /**
             * The unit short name, which must be unique within the tenant's context.
             * @example kg
             */
            'short_name' => [
                'required',
                'string',
                'max:50',
                
                TenantRule::unique(
                    'units',
                    'short_name'
                ),
            ],

            /**
             * A brief description of the unit.
             * @example Kilogram is a unit of mass in the metric system.
             */
            'description' => [
                'nullable',
                'string',
            ],

            /**
             * boolean flag indicating whether the unit is active or inactive.
             * @example true
             */
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}