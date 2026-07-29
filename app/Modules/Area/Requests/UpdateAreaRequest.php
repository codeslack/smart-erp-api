<?php

namespace App\Modules\Area\Requests;

use App\Core\Validation\TenantRule;

use App\Core\Requests\BaseRequest;

class UpdateAreaRequest extends BaseRequest
{
    public function rules(): array
    {
        $area = $this->route('area');

        return [

            'name' => [
                'sometimes',
                'string',
                'max:255',

                TenantRule::uniqueIgnore(
                    'areas',
                    'name',
                    $area->id
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
