<?php

namespace App\Modules\Rbac\Controllers;

use Illuminate\Http\JsonResponse;

use App\Http\Controllers\ApiController;

use App\Modules\Rbac\Requests\StoreRoleRequest;
use App\Modules\Rbac\Requests\UpdateRoleRequest;

use App\Modules\Rbac\Resources\RoleResource;

use App\Modules\Rbac\Services\RoleService;

class RoleController extends ApiController
{
    public function __construct(
        protected RoleService $roles
    ) {}

    public function index(): JsonResponse
    {
        $roles = $this->roles->paginate(
            request('per_page', 15)
        );

        return $this->success(
            RoleResource::collection($roles),
            'Roles retrieved successfully.'
        );
    }

    public function store(
        StoreRoleRequest $request
    ): JsonResponse {
        $role = $this->roles->create(
            $request->validated()
        );

        return $this->success(
            new RoleResource(
                $role->load('permissions')
            ),
            'Role created successfully.',
            201
        );
    }

    public function show(
        string $uuid
    ): JsonResponse {
        $role = $this->roles->findByUuid($uuid);

        return $this->success(
            new RoleResource($role),
            'Role retrieved successfully.'
        );
    }

    public function update(
        UpdateRoleRequest $request,
        string $uuid
    ): JsonResponse {
        $role = $this->roles->update(
            $uuid,
            $request->validated()
        );

        return $this->success(
            new RoleResource($role),
            'Role updated successfully.'
        );
    }

    public function destroy(
        string $uuid
    ): JsonResponse {
        $this->roles->delete($uuid);

        return $this->success(
            null,
            'Role deleted successfully.'
        );
    }
}