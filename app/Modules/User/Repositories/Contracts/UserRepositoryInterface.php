<?php

namespace App\Modules\User\Repositories\Contracts;

use App\Modules\User\Models\User;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;

interface UserRepositoryInterface
    extends BaseRepositoryInterface
{
    public function paginateWithRoles(
        int $perPage = 15
    ): LengthAwarePaginator;

    public function findByEmail(
        string $email
    ): ?User;
}