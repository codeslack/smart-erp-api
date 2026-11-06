<?php

namespace App\Modules\User\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use App\Http\Controllers\ApiController;

use App\Modules\User\Services\AuthService;
use App\Modules\User\Requests\LoginRequest;
use App\Modules\User\Resources\UserResource;

class AuthController extends ApiController
{
    public function __construct(
        protected AuthService $service
    ) {}

    /**
     * Authenticate user.
     */
    public function login(
        LoginRequest $request
    ): JsonResponse {

        $credentials = $request->validated();

        $result = $this->service->login(
            $credentials['email'],
            $credentials['password']
        );

        return $this->success([
            'user' => UserResource::make(
                $result['user']
            )->resolve(),

            'token' => $result['token'],

            'token_type' => $result['token_type'],
        ], 'Login successful.');
    }

    /**
     * Get authenticated user.
     */
    public function me(
        Request $request
    ): JsonResponse {

        $user = $this->service->me(
            $request->user()
        );

        return $this->success(
            UserResource::make($user),
            'Authenticated user retrieved successfully.'
        );
    }

    /**
     * Logout authenticated user.
     */
    public function logout(
        Request $request
    ): JsonResponse {

        $this->service->logout(
            $request->user()
        );

        return $this->success(
            null,
            'Logged out successfully.'
        );
    }
}