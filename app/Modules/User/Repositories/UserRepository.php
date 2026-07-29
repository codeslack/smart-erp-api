<?php

namespace App\Modules\User\Repositories;

use App\Core\Repositories\BaseRepository;

use App\Modules\User\Models\User;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use App\Modules\User\Repositories\Contracts\UserRepositoryInterface;

class UserRepository
    extends BaseRepository
    implements UserRepositoryInterface
{
    public function __construct(
        User $model
    ) {
        parent::__construct($model);
    }

    public function paginateWithRoles(
        int $perPage = 15
    ): LengthAwarePaginator {

        return $this->model
            ->newQuery()
            ->with('roles')
            ->latest()
            ->paginate($perPage);
    }

    public function findByEmail(
        string $email
    ): ?User
    {
        return $this->model
            ->newQuery()
            ->where('email', $email)
            ->first();
    }
}