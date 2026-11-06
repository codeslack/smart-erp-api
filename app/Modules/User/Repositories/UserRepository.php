<?php

namespace App\Modules\User\Repositories;

use App\Core\Repositories\BaseRepository;

use App\Modules\User\Models\User;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use App\Core\Scopes\TenantScope;

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
    ): ?User {

        return $this->model
            ->newQuery()
            ->where('email', $email)
            ->first();
    }

    /**
     * Authentication lookup before tenant initialization.
     *
     * Only TenantScope is bypassed.
     * SoftDeletes and other model behavior remain active.
     */
    public function findByEmailForAuthentication(
        string $email
    ): ?User {

        return $this->model
            ->newQuery()
            ->withoutGlobalScope(TenantScope::class)
            ->where('email', $email)
            ->first();
    }
}