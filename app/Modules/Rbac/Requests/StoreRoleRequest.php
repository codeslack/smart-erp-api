<?php

namespace App\Modules\Rbac\Requests;

use App\Core\Requests\BaseRequest;
use App\Core\Validation\TenantRule;

class StoreRoleRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                TenantRule::unique(
                    'roles',
                    'name'
                )->where('guard_name', 'sanctum'),
            ],
        ];
    }
}