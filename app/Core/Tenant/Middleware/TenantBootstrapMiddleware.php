<?php

namespace App\Core\Tenant\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Relations\Relation;
use Symfony\Component\HttpFoundation\Response;

use Laravel\Sanctum\PersonalAccessToken;

use App\Core\Tenant\TenantManager;
use App\Core\Scopes\TenantScope;

use App\Modules\User\Models\User;
use App\Modules\Tenant\Models\Tenant;

class TenantBootstrapMiddleware
{
    public function __construct(
        protected TenantManager $manager
    ) {}

    public function handle(
        Request $request,
        Closure $next
    ): Response {

        $plainTextToken = $request->bearerToken();

        // Login and requests without bearer tokens
        // continue normally.
        if (! $plainTextToken) {
            return $next($request);
        }

        // Verify the Sanctum token.
        $accessToken = PersonalAccessToken::findToken(
            $plainTextToken
        );

        if (! $accessToken) {
            return $next($request);
        }

        // Resolve possible morph-map aliases.
        $tokenableType = Relation::getMorphedModel(
            $accessToken->tokenable_type
        ) ?? $accessToken->tokenable_type;

        if ($tokenableType !== User::class) {
            return $next($request);
        }

        // Retrieve the user without only the tenant scope.
        // SoftDeletes remains active.
        $user = User::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereKey($accessToken->tokenable_id)
            ->first();

        if (
            ! $user ||
            ! $user->isActive() ||
            ! $user->tenant_id
        ) {
            return $next($request);
        }

        // Never accept tenant ID from the client.
        $tenant = Tenant::query()
            ->whereKey($user->tenant_id)
            ->where('is_active', true)
            ->first();

        if (! $tenant) {
            return $next($request);
        }

        // Establish tenant context before Sanctum authentication.
        $this->manager->setTenant($tenant);

        try {
            return $next($request);
        } finally {
            $this->manager->clear();
        }
    }
}