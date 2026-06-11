<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\User;
use App\Modules\User\Services\UserService;
use App\Modules\User\Requests\StoreUserRequest;
use App\Modules\User\Resources\UserResource;

class UserController extends Controller
{
    public function __construct(
        private UserService $service
    ) {}

    public function index()
    {
        return UserResource::collection(
            User::latest()->paginate()
        );
    }

    public function store(
        StoreUserRequest $request
    ) {
        $user = $this->service->create([
            'tenant_id' => tenant()->id,
            ...$request->validated(),
        ]);

        return new UserResource($user);
    }

    public function show(User $user)
    {
        return new UserResource($user);
    }

    public function update(
        StoreUserRequest $request,
        User $user
    ) {
        $user->update(
            $request->validated()
        );

        return new UserResource($user);
    }

    public function destroy(User $user)
    {
        $this->service->delete($user);

        return response()->json([
            'message' => 'User deleted'
        ]);
    }
}