<?php

namespace App\Modules\User\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Modules\User\Models\User;
use App\Modules\Tenant\Models\Tenant;

use App\Modules\Rbac\Enums\RoleEnum;
use App\Modules\Rbac\Services\UserRoleService;
use App\Modules\Rbac\Repositories\Contracts\RoleRepositoryInterface;

use App\Modules\SystemNumber\Enums\SystemNumberTypeEnum;

use App\Modules\User\Repositories\Contracts\UserRepositoryInterface;

class UserService
{
    public function __construct(
        protected UserRepositoryInterface $repository,
        protected UserRoleService $userRoles,
        protected RoleRepositoryInterface $roles,
    ) {}

    public function paginate(
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->repository->paginateWithRoles($perPage);
    }

    public function create(array $data): User
    {
        $roleUuids = $data['role_uuids'] ?? [];

        unset($data['role_uuids']);

        $data['code'] ??= nextSystemNumber(
            SystemNumberTypeEnum::USER
        );

        return DB::transaction(function () use (
            $data,
            $roleUuids
        ) {
            $user = $this->repository->create($data);

            return $this->userRoles->syncRoles(
                $user->uuid,
                $roleUuids
            );
        });
    }

    public function createAdmin(
        Tenant $tenant,
        array $data
    ): User {
        return DB::transaction(function () use (
            $tenant,
            $data
        ) {
            $user = $this->repository->create([
                'tenant_id' => $tenant->id,

                'code' => nextSystemNumber(
                    SystemNumberTypeEnum::USER,
                    $tenant->id
                ),

                'name' => $data['admin_name'],

                'email' => $data['admin_email'],

                'password' => $data['admin_password'],

                'is_active' => true,
            ]);

            $administratorRole = $this->roles->findByName(
                RoleEnum::ADMINISTRATOR->value,
                'sanctum'
            );

            if (! $administratorRole) {
                throw new ModelNotFoundException(
                    'Administrator role not found for this tenant.'
                );
            }

            return $this->userRoles->syncRoles(
                $user->uuid,
                [$administratorRole->uuid]
            );
        });
    }

    public function update(
        User $user,
        array $data
    ): User {
        $hasRoles = array_key_exists('role_uuids', $data);

        $roleUuids = $data['role_uuids'] ?? [];

        unset($data['role_uuids']);

        if (
            array_key_exists('password', $data)
            && empty($data['password'])
        ) {
            unset($data['password']);
        }

        return DB::transaction(function () use (
            $user,
            $data,
            $hasRoles,
            $roleUuids
        ) {
            $user = $this->repository->update(
                $user,
                $data
            );

            if ($hasRoles) {
                return $this->userRoles->syncRoles(
                    $user->uuid,
                    $roleUuids
                );
            }

            return $this->userRoles->findUserRoles(
                $user->uuid
            );
        });
    }

    public function delete(User $user): bool
    {
        return DB::transaction(function () use ($user) {

            $this->userRoles->syncRoles(
                $user->uuid,
                []
            );

            return $this->repository->delete($user);
        });
    }

    public function find(int $id): ?User
    {
        return $this->repository->findById($id);
    }

    public function findByUuid(string $uuid): ?User
    {
        return $this->repository->findByUuid($uuid);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->repository->findByEmail($email);
    }
}
