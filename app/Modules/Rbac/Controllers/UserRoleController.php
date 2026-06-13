<?php

namespace App\Modules\Rbac\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Modules\User\Models\User;

class UserRoleController extends Controller
{
    /**
     * GET /rbac/users/{user}/roles
     */
    public function show(User $user)
    {
        return response()->json([
            'success' => true,
            'data' => $user->roles,
        ]);
    }

    /**
     * POST /rbac/users/{user}/roles
     */
    public function sync(Request $request, User $user)
    {
        $validated = $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => ['string'],
        ]);

        $user->syncRoles($validated['roles']);

        return response()->json([
            'success' => true,
            'message' => 'Roles assigned successfully',
            'data' => $user->fresh()->roles,
        ]);
    }
}