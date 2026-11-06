<?php

namespace App\Modules\Rbac\Controllers;

use Illuminate\Http\JsonResponse;

use App\Http\Controllers\ApiController;

use App\Modules\Rbac\Requests\AssignPermissionsRequest;

use App\Modules\Rbac\Resources\RoleResource;
use App\Modules\Rbac\Resources\PermissionResource;

use App\Modules\Rbac\Services\RolePermissionService;

class RolePermissionController extends ApiController
{
    public function __construct(
        protected RolePermissionService $permissions
    ) {}

    public function index(): JsonResponse
    {
        $permissions = $this->permissions
            ->paginatePermissions(
                request('per_page', 15)
            );

        return $this->success(
            PermissionResource::collection($permissions),
            'Permissions retrieved successfully.'
        );
    }

    public function show(
        string $uuid
    ): JsonResponse {
        $role = $this->permissions
            ->findRolePermissions($uuid);

        return $this->success(
            new RoleResource($role),
            'Role permissions retrieved successfully.'
        );
    }

    public function sync(
        AssignPermissionsRequest $request,
        string $uuid
    ): JsonResponse {
        $role = $this->permissions->syncPermissions(
            $uuid,
            $request->validated('permissions')
        );

        return $this->success(
            new RoleResource($role),
            'Role permissions updated successfully.'
        );
    }
}