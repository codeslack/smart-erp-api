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
        $tenantId = tenant()?->id;

        if (! $tenantId) {
            return $next($request);
        }

        $registrar = app(PermissionRegistrar::class);

        $registrar->setPermissionsTeamId($tenantId);

        try {
            return $next($request);
        } finally {
            // Clear explicit team context.
            // TenantTeamResolver will fall back to tenant().
            $registrar->setPermissionsTeamId(null);
        }
    }
}