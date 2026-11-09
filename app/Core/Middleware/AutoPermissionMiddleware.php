<?php

namespace App\Core\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpFoundation\Response;

use App\Modules\Rbac\Models\Permission;

class AutoPermissionMiddleware
{
    private const GUARD_NAME = 'sanctum';

    private const ACTION_PERMISSIONS = [
        'index'   => 'view',
        'show'    => 'view',
        'store'   => 'create',
        'update'  => 'update',
        'destroy' => 'delete',
    ];

    public function handle(
        Request $request,
        Closure $next,
        ?string $override = null
    ): Response {

        $user = $request->user();

        if (! $user) {
            throw new AuthorizationException(
                'Authentication required.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Resolve Permission
        |--------------------------------------------------------------------------
        */

        if ($override) {

            $permission = $override;

        } else {

            $route = $request->route();

            $controller = $route?->getControllerClass();

            $action = $route?->getActionMethod();

            if (! $controller || ! $action) {
                throw new AuthorizationException(
                    'Permission could not be determined.'
                );
            }

            $controllerName = class_basename($controller);

            $moduleName = Str::beforeLast(
                $controllerName,
                'Controller'
            );

            $prefix = Str::kebab($moduleName);

            $permissionAction =
                self::ACTION_PERMISSIONS[$action] ?? null;

            if (! $permissionAction) {
                throw new AuthorizationException(
                    'No permission mapping exists for this action.'
                );
            }

            $permission = $prefix . '.' . $permissionAction;
        }

        /*
        |--------------------------------------------------------------------------
        | Verify Permission Exists
        |--------------------------------------------------------------------------
        */

        $exists = Permission::query()
            ->where('name', $permission)
            ->where('guard_name', self::GUARD_NAME)
            ->exists();

        if (! $exists) {
            throw new AuthorizationException(
                'Required permission is not registered.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Check User Permission
        |--------------------------------------------------------------------------
        */

        if (! $user->hasPermissionTo(
            $permission,
            self::GUARD_NAME
        )) {
            throw new AuthorizationException(
                'You do not have permission to perform this action.'
            );
        }

        return $next($request);
    }
}
