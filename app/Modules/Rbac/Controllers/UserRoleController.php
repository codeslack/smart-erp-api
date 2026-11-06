<?php

namespace App\Modules\Rbac\Controllers;

use Illuminate\Http\JsonResponse;

use App\Http\Controllers\ApiController;

use App\Modules\Rbac\Requests\AssignRolesRequest;
use App\Modules\Rbac\Resources\RoleResource;
use App\Modules\Rbac\Services\UserRoleService;

class UserRoleController extends ApiController
{
    public function __construct(
        protected UserRoleService $userRoles
    ) {}

    public function show(
        string $userUuid
    ): JsonResponse {
        $user = $this->userRoles->findUserRoles(
            $userUuid
        );

        return $this->success(
            RoleResource::collection(
                $user->roles
            ),
            'User roles retrieved successfully.'
        );
    }

    public function sync(
        AssignRolesRequest $request,
        string $userUuid
    ): JsonResponse {
        $user = $this->userRoles->syncRoles(
            $userUuid,
            $request->validated('roles')
        );

        return $this->success(
            RoleResource::collection(
                $user->roles
            ),
            'User roles updated successfully.'
        );
    }
}