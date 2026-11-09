<?php

namespace App\Modules\Rbac\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use Spatie\Permission\PermissionRegistrar;

use App\Core\Services\BaseService;
use App\Core\Exceptions\BusinessException;

use App\Modules\Rbac\Models\Role;

use App\Modules\Rbac\Repositories\Contracts\RoleRepositoryInterface;

class RoleService extends BaseService
{
    private const GUARD_NAME = 'sanctum';

    public function __construct(
        protected RoleRepositoryInterface $roles,
        protected PermissionRegistrar $registrar,
    ) {}

    public function paginate(
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->roles->paginateWithPermissions(
            $perPage
        );
    }

    public function create(
        array $data
    ): Role {
        $name = trim($data['name']);

        if (
            $this->roles->existsByName(
                $name,
                self::GUARD_NAME
            )
        ) {
            throw new BusinessException(
                'A role with this name already exists.'
            );
        }

        return DB::transaction(function () use ($name) {
            return $this->roles->create([
                'name' => $name,
                'guard_name' => self::GUARD_NAME,
            ]);
        });
    }

    public function findByUuid(
        string $uuid
    ): Role {
        return $this->roles
            ->findByUuidWithPermissions($uuid)
            ?? throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
    }

    public function update(
        string $uuid,
        array $data
    ): Role {
        $role = $this->roles->findOrFail($uuid);

        $name = trim($data['name']);

        if (
            $this->roles->existsByName(
                $name,
                self::GUARD_NAME,
                $role->id
            )
        ) {
            throw new BusinessException(
                'A role with this name already exists.'
            );
        }

        return DB::transaction(function () use (
            $role,
            $name
        ) {
            $this->roles->update(
                $role,
                ['name' => $name]
            );

            return $role->refresh()->load('permissions');
        });
    }

    public function delete(string $uuid): bool
    {
        $role = $this->roles->findOrFail($uuid);

        $tenantId = tenant()?->id;

        if (! $tenantId) {
            throw new BusinessException(
                'A tenant context is required to delete a role.'
            );
        }

        return DB::transaction(function () use ($role, $tenantId) {
            $this->registrar->setPermissionsTeamId($tenantId);

            try {
                if ($role->users()->exists()) {
                    throw new BusinessException(
                        'This role cannot be deleted while it is assigned to users.'
                    );
                }

                return $this->roles->delete($role);
            } finally {
                $this->registrar->setPermissionsTeamId(null);
            }
        });
    }
}