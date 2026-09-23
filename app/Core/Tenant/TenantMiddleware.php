<?php

namespace App\Core\Tenant;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;


class TenantMiddleware
{
    public function __construct(
        protected TenantResolver $resolver,
        protected TenantManager $manager
    ) {}

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        if (! Auth::check()) {
            return response()->json([
                'success' => false,
                'code' => 'UNAUTHENTICATED',
                'message' => 'Authentication required.',
            ], 401);
        }

        $user = Auth::user();

        if (! $user->tenant_id) {
            return response()->json([
                'success' => false,
                'code' => 'TENANT_REQUIRED',
                'message' => 'No tenant is assigned to this user.',
            ], 403);
        }

        $tenant = $this->resolver->resolve();

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'code' => 'TENANT_NOT_FOUND',
                'message' => 'Tenant not found or inactive.',
            ], 404);
        }

        $this->manager->setTenant($tenant);

        try {
            return $next($request);
        } finally {
            $this->manager->clear();
        }
    }
}