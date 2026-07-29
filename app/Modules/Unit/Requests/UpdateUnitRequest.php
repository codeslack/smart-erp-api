<?php

namespace App\Modules\Unit\Requests;

use App\Core\Validation\TenantRule;

use App\Core\Requests\BaseRequest;

class UpdateUnitRequest extends BaseRequest
{
    public function rules(): array
    {
        $unit = $this->route('unit');

        return [

            'name' => [
                'sometimes',
                'string',
                'max:255',

                TenantRule::uniqueIgnore(
                    'units',
                    'name',
                    $unit->id
                ),
            ],

            'short_name' => [
                'sometimes',
                'string',
                'max:50',
                
                TenantRule::uniqueIgnore(
                    'units',
                    'short_name',
                    $unit->id
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