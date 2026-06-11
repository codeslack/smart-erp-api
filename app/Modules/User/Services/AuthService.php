<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Modules\Tenant\Models\Tenant;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function login(
        array $credentials
    ): array {

        $tenant = Tenant::where(
            'slug',
            $credentials['tenant']
        )->first();

        if (! $tenant) {
            throw ValidationException::withMessages([
                'tenant' => [
                    'Tenant not found'
                ]
            ]);
        }

        $user = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', $credentials['email'])
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
