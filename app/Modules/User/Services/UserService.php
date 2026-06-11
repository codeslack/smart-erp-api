<?php

namespace App\Modules\User\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\User\Models\User;
use App\Modules\User\Repositories\Contracts\UserRepositoryInterface;

class UserService
{
    public function __construct(
        private UserRepositoryInterface $repository
    ) {}

    public function create(array $data): User
    {
        return DB::transaction(
            fn() => $this->repository->create($data)
        );
    }

    public function update(
        User $user,
        array $data
    ): User {
        return DB::transaction(
            fn() => $this->repository->update(
                $user,
                $data
            )
        );
    }

    public function delete(User $user): bool
    {
        return DB::transaction(
            fn() => $this->repository->delete($user)
        );
    }
}