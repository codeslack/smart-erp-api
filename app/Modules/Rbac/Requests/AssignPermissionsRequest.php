<?php

namespace App\Modules\Rbac\Requests;

use App\Core\Requests\BaseRequest;

class AssignPermissionsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'permissions' => [
                'present',
                'array',
            ],

            'permissions.*' => [
                'required',
                'string',
                'uuid',
                'distinct:strict',
            ],
        ];
    }
}