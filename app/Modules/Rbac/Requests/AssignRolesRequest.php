<?php

namespace App\Modules\Rbac\Requests;

use App\Core\Requests\BaseRequest;

class AssignRolesRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'roles' => [
                'present',
                'array',
            ],

            'roles.*' => [
                'required',
                'string',
                'uuid',
                'distinct:strict',
            ],
        ];
    }
}