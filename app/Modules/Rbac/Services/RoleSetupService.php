<?php

namespace App\Modules\Rbac\Services;

use App\Modules\Rbac\Models\Role;
use App\Modules\Tenant\Models\Tenant;

use App\Modules\Rbac\Enums\RoleEnum;

use App\Modules\Tenant\Contracts\TenantSetupInterface;

class RoleSetupService
    implements TenantSetupInterface
{
    public function setup(
        Tenant $tenant
    ): void {

        foreach (
            $this->defaultRoles()
            as $role
        ) {

            Role::firstOrCreate(
                [
                    'tenant_id' =>
                        $tenant->id,

                    'name' =>
                        $role,
                ],
                [
                    'guard_name' =>
                        'sanctum',
                ]
            );
        }
    }

    protected function defaultRoles(): array
    {
        return RoleEnum::values();
    }
}
