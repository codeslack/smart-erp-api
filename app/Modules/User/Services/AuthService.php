<?php

namespace App\Modules\User\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

use Laravel\Sanctum\PersonalAccessToken;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;

use App\Core\Tenant\TenantManager;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;

use App\Modules\User\Repositories\Contracts\UserRepositoryInterface;

class AuthService
{
    public function __construct(
        protected UserRepositoryInterface $users,
        protected TenantManager $tenantManager,
    ) {}

    /**
     * Authenticate user and generate Sanctum token.
     */
    public function login(
        string $email,
        string $password
    ): array {

        $user = $this->users
            ->findByEmailForAuthentication(
                trim($email)
            );

        if (
            ! $user ||
            ! Hash::check(
                $password,
                $user->getAuthPassword()
            )
        ) {
            throw new AuthenticationException(
                'Invalid email or password.'
            );
        }

        if (! $user->isActive()) {
            throw new AuthorizationException(
                'Your account is inactive.'
            );
        }

        if (! $user->tenant_id) {
            throw new AuthorizationException(
                'No tenant is assigned to this user.'
            );
        }

        $tenant = Tenant::query()
            ->whereKey($user->tenant_id)
            ->where('is_active', true)
            ->first();

        if (! $tenant) {
            throw new AuthorizationException(
                'Your tenant is inactive or unavailable.'
            );
        }

        $this->tenantManager->setTenant($tenant);

        try {

            return DB::transaction(
                function () use ($user): array {

                    $user->forceFill([
                        'last_login_at' => now(),
                    ])->save();

                    $token = $user->createToken(
                        'auth-token'
                    )->plainTextToken;

                    return [
                        'user' => $user,
                        'token' => $token,
                        'token_type' => 'Bearer',
                    ];
                }
            );

        } finally {

            $this->tenantManager->clear();

        }
    }

    /**
     * Return authenticated user.
     */
    public function me(User $user): User
    {
        return $user;
    }

    /**
     * Revoke current access token.
     */
    public function logout(User $user): void
    {
        /** @var PersonalAccessToken|null $token */
        $token = $user->currentAccessToken();

        $token?->delete();
    }
}