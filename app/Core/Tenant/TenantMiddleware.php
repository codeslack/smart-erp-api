<?php

namespace App\Core\Tenant;

use Closure;
use Illuminate\Http\Request;

class TenantMiddleware
{
    public function __construct(
        protected TenantResolver $resolver,
        protected TenantManager $manager
    ) {}

    public function handle(
        Request $request,
        Closure $next
    ) {
        $tenant = $this->resolver->resolve();

        if (!$tenant) {
            return response()->json([
                'message' => 'Tenant not found'
            ], 404);
        }

        $this->manager->setTenant($tenant);

        app()->instance(
            TenantManager::class,
            $this->manager
        );

        return $next($request);
    }
}