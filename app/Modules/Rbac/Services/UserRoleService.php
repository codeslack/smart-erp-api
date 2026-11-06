<?php

namespace App\Modules\Rbac\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Spatie\Permission\PermissionRegistrar;

use App\Core\Services\BaseService;
use App\Core\Exceptions\BusinessException;

use App\Modules\User\Models\User;

use App\Modules\Rbac\Repositories\Contracts\RoleRepositoryInterface;
use App\Modules\User\Repositories\Contracts\UserRepositoryInterface;

class UserRoleService extends BaseService
{
    public function __construct(
        protected UserRepositoryInterface $users,
        protected RoleRepositoryInterface $roles,
        protected PermissionRegistrar $registrar
    ) {}

    public function findUserRoles(string $userUuid): User
    {
        $user = $this->users->findByUuid($userUuid);

        if (! $user) {
            throw new ModelNotFoundException();
        }

        return $this->withTenantTeam(
            fn () => $user->load('roles')
        );
    }

    public function syncRoles(
        string $userUuid,
        array $roleUuids
    ): User {
        return DB::transaction(function () use (
            $userUuid,
            $roleUuids
        ) {
            $user = $this->users->findByUuid($userUuid);

            if (! $user) {
                throw new ModelNotFoundException();
            }

            $tenantId = tenant()?->id;

            if (! $tenantId) {
                throw new BusinessException(
                    'A tenant context is required to assign roles.'
                );
            }

            $roleUuids = array_values(array_unique($roleUuids));

            $roles = collect();

            foreach ($roleUuids as $uuid) {
                $role = $this->roles->findByUuid($uuid);

                if (! $role) {
                    throw new BusinessException(
                        'One or more selected roles are invalid for this tenant.'
                    );
                }

                $roles->push($role);
            }

            return $this->withTenantTeam(function () use (
                $user,
                $roles
            ) {
                $user->syncRoles($roles);

                return $user->fresh()->load('roles');
            });
        });
    }

    private function withTenantTeam(callable $callback): mixed
    {
        $tenantId = tenant()?->id;

        if (! $tenantId) {
            throw new BusinessException('A tenant context is required.');
        }

        $this->registrar->setPermissionsTeamId($tenantId);

        try {
            return $callback();
        } finally {
            $this->registrar->setPermissionsTeamId(null);
        }
    }
}