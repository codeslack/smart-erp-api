<?php

namespace App\Modules\Rbac\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Rbac\Models\Role;
use App\Modules\Rbac\Models\Permission;
use App\Modules\Rbac\Requests\AssignPermissionsRequest;
use App\Modules\Rbac\Resources\PermissionResource;
use Illuminate\Http\JsonResponse;

class RolePermissionController extends Controller
{
    /**
     * GET /rbac/permissions
     */
    public function index(): JsonResponse
    {
        $permissions = Permission::query()
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => PermissionResource::collection($permissions),
        ]);
    }

    /**
     * GET /rbac/roles/{role}/permissions
     */
    public function show(Role $role): JsonResponse
    {
        abort_if(
            $role->tenant_id !== tenant()->id,
            403
        );

        $permissions = $role->permissions()
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => PermissionResource::collection($permissions),
        ]);
    }

    /**
     * POST /rbac/roles/{role}/permissions
     */
    public function sync(
        AssignPermissionsRequest $request,
        Role $role
    ): JsonResponse {
        abort_if(
            $role->tenant_id !== tenant()->id,
            403
        );

        $role->syncPermissions(
            $request->validated('permissions')
        );

        return response()->json([
            'success' => true,
            'message' => 'Permissions assigned successfully.',
            'data' => PermissionResource::collection(
                $role->permissions()->orderBy('name')->get()
            ),
        ]);
    }
}
