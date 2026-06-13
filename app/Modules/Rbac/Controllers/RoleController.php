<?php

namespace App\Modules\Rbac\Controllers;

use App\Modules\Rbac\Models\Role;
use App\Http\Controllers\Controller;
use App\Modules\Rbac\Resources\RoleResource;
use App\Modules\Rbac\Requests\StoreRoleRequest;
use App\Modules\Rbac\Requests\UpdateRoleRequest;

class RoleController extends Controller
{
    /**
     * GET /rbac/roles
     */
    public function index()
    {
        return RoleResource::collection(
            Role::with('permissions')
                ->where('tenant_id', tenant()->id)
                ->orderBy('name')
                ->get()
        );
    }

    /**
     * POST /rbac/roles
     */
    public function store(StoreRoleRequest $request)
    {
        $role = Role::create([
            'tenant_id' => tenant()->id,
            'name' => $request->name,
            'guard_name' => 'sanctum',
        ]);

        return new RoleResource($role);
    }

    /**
     * GET /rbac/roles/{role}
     */
    public function show(Role $role)
    {
        $this->checkTenant($role);

        return new RoleResource(
            $role->load('permissions')
        );
    }

    /**
     * PUT /rbac/roles/{role}
     */
    public function update(
        UpdateRoleRequest $request,
        Role $role
    ) {
        $this->checkTenant($role);

        $role->update([
            'name' => $request->name,
        ]);

        return new RoleResource($role);
    }

    /**
     * DELETE /rbac/roles/{role}
     */
    public function destroy(Role $role)
    {
        $this->checkTenant($role);

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully',
        ]);
    }

    /**
     * Check if the role belongs to the current tenant.
     *
     * @param Role $role
     * @return void
     */
    protected function checkTenant(Role $role): void
    {
        abort_if(
            $role->tenant_id !== tenant()->id,
            403
        );
    }
}
