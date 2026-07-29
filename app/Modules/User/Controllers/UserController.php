<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Models\User;

use App\Modules\User\Services\UserService;

use App\Modules\User\Requests\StoreUserRequest;
use App\Modules\User\Requests\UpdateUserRequest;

use Illuminate\Http\JsonResponse;
use App\Modules\User\Resources\UserResource;

use App\Http\Controllers\ApiController;

class UserController extends ApiController
{
    public function __construct(
        protected UserService $service
    ) {}

    public function index(): JsonResponse
    {
        $users = $this->service
            ->paginate(
                request(
                    'per_page',
                    15
                )
            );

        return $this->success(
            UserResource::collection(
                $users
            ),
            'Users retrieved successfully.'
        );
    }

    public function store(
        StoreUserRequest $request
    ): JsonResponse {
        $user = $this->service->create(
            $request->validated()
        );

        return $this->success(
            new UserResource(
                $user->load('roles')
            ),
            'User created successfully.',
            201
        );
    }

    public function show(
        User $user
    ): JsonResponse {
        return $this->success(
            new UserResource(
                $user->load('roles')
            ),
            'User retrieved successfully.'
        );
    }

    public function update(
        UpdateUserRequest $request,
        User $user
    ): JsonResponse {
        $user = $this->service->update(
            $user,
            $request->validated()
        );

        return $this->success(
            new UserResource(
                $user->load('roles')
            ),
            'User updated successfully.'
        );
    }

    public function destroy(
        User $user
    ): JsonResponse {
        $this->service->delete(
            $user
        );

        return $this->success(
            null,
            'User deleted successfully.'
        );
    }
}
