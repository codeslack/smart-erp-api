<?php

namespace App\Modules\User\Repositories;

use App\Modules\User\Models\User;
use App\Modules\User\Repositories\Contracts\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface
{
    public function paginate()
    {
        return User::latest()->paginate();
    }

    public function find(int $id): ?User
    {
        return User::find($id);
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh();
    }

    public function delete(User $user): bool
    {
        return $user->delete();
    }

    public function findByEmail(
        int $tenantId,
        string $email
    ): ?User {
        return User::where(
            'tenant_id',
            $tenantId
        )
        ->where(
            'email',
            $email
        )
        ->first();
    }
}