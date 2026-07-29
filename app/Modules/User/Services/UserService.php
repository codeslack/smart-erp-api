<?php

namespace App\Modules\User\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Permission\PermissionRegistrar;

use App\Modules\User\Models\User;
use App\Modules\Tenant\Models\Tenant;

use App\Modules\Rbac\Models\Role;

use App\Modules\Rbac\Enums\RoleEnum;
use App\Modules\SystemNumber\Enums\SystemNumberTypeEnum;

use App\Modules\User\Repositories\Contracts\UserRepositoryInterface;

class UserService
{
    public function __construct(
        protected UserRepositoryInterface $repository,
    ) {}

    public function paginate(
        int $perPage = 15
    ): LengthAwarePaginator {

        return $this->repository
            ->paginateWithRoles(
                $perPage
            );
    }

    public function create(
        array $data
    ): User {

        return DB::transaction(
            function () use ($data) {

                $roleIds =
                    $data['role_ids'] ?? [];

                unset(
                    $data['role_ids']
                );

                $data['code'] ??=
                    nextSystemNumber(
                        SystemNumberTypeEnum::USER
                    );

                $user =
                    $this->repository->create(
                        $data
                    );

                if (
                    ! empty($roleIds)
                ) {

                    $user->syncRoles(
                        Role::query()
                            ->whereIn(
                                'id',
                                $roleIds
                            )
                            ->get()
                    );
                }

                return $user->fresh();
            }
        );
    }

    public function createAdmin(
        Tenant $tenant,
        array $data
    ): User {

        return DB::transaction(
            function () use (
                $tenant,
                $data
            ) {

                $user =
                    $this->repository->create([

                        'tenant_id' =>
                            $tenant->id,

                        'code' =>
                            nextSystemNumber(
                                SystemNumberTypeEnum::USER,
                                $tenant->id
                            ),

                        'name' =>
                            $data['admin_name'],

                        'email' =>
                            $data['admin_email'],

                        'password' =>
                            $data['admin_password'],

                        'is_active' =>
                            true,
                    ]);

                $administratorRole =
                    Role::query()

                        ->where(
                            'tenant_id',
                            $tenant->id
                        )

                        ->where(
                            'name',
                            RoleEnum::ADMINISTRATOR->value
                        )

                        ->firstOrFail();

                app( PermissionRegistrar::class )
                    ->setPermissionsTeamId(
                        $tenant->id
                    );

                logger()->info(
                    'Spatie Team ID',
                    [
                        'team_id' => app(
                            PermissionRegistrar::class
                        )->getPermissionsTeamId(),
                    ]
                );

                $user->assignRole(
                    $administratorRole
                );

                return $user->fresh();
            }
        );
    }

    public function update(
        User $user,
        array $data
    ): User {

        return DB::transaction(
            function () use (
                $user,
                $data
            ) {

                $roleIds =
                    $data['role_ids'] ?? null;

                unset(
                    $data['role_ids']
                );

                if (
                    array_key_exists(
                        'password',
                        $data
                    )
                    &&
                    empty(
                        $data['password']
                    )
                ) {

                    unset(
                        $data['password']
                    );
                }

                $user =
                    $this->repository->update(
                        $user,
                        $data
                    );

                if (
                    $roleIds !== null
                ) {

                    $user->syncRoles(
                        Role::query()
                            ->whereIn(
                                'id',
                                $roleIds
                            )
                            ->get()
                    );
                }

                return $user->fresh();
            }
        );
    }

    public function delete(
        User $user
    ): bool {

        return DB::transaction(
            function () use ($user) {

                $user->syncRoles([]);

                return $this->repository
                    ->delete($user);
            }
        );
    }

    public function find(
        int $id
    ): ?User {

        return $this->repository
            ->findById($id);
    }

    public function findByUuid(
        string $uuid
    ): ?User {

        return $this->repository
            ->findByUuid($uuid);
    }

    public function findByEmail(
        string $email
    ): ?User {

        return $this->repository
            ->findByEmail($email);
    }
}
