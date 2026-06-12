<?php

namespace App\Core\Tenant;

use Spatie\Permission\Contracts\PermissionsTeamResolver;

class TenantTeamResolver implements PermissionsTeamResolver
{
    protected int|string|null $tenantId = null;

    public function getPermissionsTeamId(): int|string|null
    {
        return $this->tenantId
            ?? tenant()?->id;
    }

    public function setPermissionsTeamId($id): void
    {
        $this->tenantId = $id;
    }
}