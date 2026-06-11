<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;

use App\Modules\User\Services\AuthService;
use App\Modules\User\Requests\LoginRequest;
use App\Modules\User\Resources\UserResource;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $service
    ) {}

    public function login(
        LoginRequest $request
    ) {

        $result = $this->service->login(
            $request->validated()
        );

        return response()->json([
            'token' => $result['token'],
            'user' => new UserResource(
                $result['user']
            ),
        ]);
    }

    public function me()
    {
        return new UserResource(
            auth()->user()
        );
    }

    public function logout()
    {
        $this->service->logout(
            auth()->user()
        );

        return response()->json([
            'message' => 'Logged out'
        ]);
    }
}