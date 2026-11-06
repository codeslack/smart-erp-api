<?php

namespace App\Modules\Rbac\Requests;

use App\Core\Requests\BaseRequest;
use App\Core\Validation\TenantRule;

use App\Modules\Rbac\Repositories\Contracts\RoleRepositoryInterface;

class UpdateRoleRequest extends BaseRequest
{
    public function rules(): array
    {
        $roleUuid = $this->route('uuid');

        $role = app(RoleRepositoryInterface::class)
            ->findByUuid($roleUuid);

        if (! $role) {
            abort(404);
        }

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                TenantRule::uniqueIgnore(
                    'roles',
                    'name',
                    $role->getKey()
                )->where('guard_name', 'sanctum'),
            ],
        ];
    }
}