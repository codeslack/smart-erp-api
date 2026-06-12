<?php

namespace App\Core\Tenant\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

class SetPermissionTenant
{
    public function handle(
        Request $request,
        Closure $next
    ) {
        if (tenant()) {
            app(PermissionRegistrar::class)
                ->setPermissionsTeamId(
                    tenant()->id
                );
        }

        return $next($request);
    }
}
