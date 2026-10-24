<?php

namespace App\Modules\User\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

use Laravel\Sanctum\PersonalAccessToken;

use App\Core\Scopes\TenantScope;

use App\Modules\User\Models\User;
use App\Modules\Tenant\Models\Tenant;

class AuthService
{
    public function login(
        array $credentials
    ): array {

        $tenant = Tenant::query()
            ->where('slug', $credentials['tenant'])
            ->where('is_active', true)
            ->first();

        if (! $tenant) {
            throw ValidationException::withMessages([
                'tenant' => [
                    'Tenant not found'
                ]
            ]);
        }

        // Tenant context is not established until after login. Bypass only the
        // tenant scope, while retaining SoftDeletes and any other model scopes.
        $user = User::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('email', $credentials['email'])
            ->where('is_active', true)
            ->first();

        if (
            ! $user ||
            ! Hash::check(
                $credentials['password'],
                $user->password
            )
        ) {
            throw ValidationException::withMessages([
                'email' => [
                    'Invalid credentials'
                ]
            ]);
        }

        $token = $user->createToken(
            'erp-api'
        )->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function logout(
        User $user
    ): void {
        /** @var PersonalAccessToken|null $token */
        $token = $user->currentAccessToken();
        
        $token?->delete();
    }
}
