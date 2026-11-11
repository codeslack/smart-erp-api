<?php

namespace App\Modules\Rbac\Models;

use App\Core\Exceptions\BusinessException;
use App\Core\Traits\HasUuidPrimaryKey;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'name',
        'guard_name',
    ];

    protected static function booted(): void
    {
        static::creating(function ($role): void {
            $tenantId = tenant()?->id;

            if (! $tenantId) {
                throw new BusinessException(
                    'A tenant context is required to create a role.',
                    'TENANT_REQUIRED'
                );
            }

            if (! $role->tenant_id) {
                $role->tenant_id = $tenantId;
            }

            if ((int) $role->tenant_id !== (int) $tenantId) {
                throw new BusinessException(
                    'Cannot create a role for another tenant.',
                    'TENANT_OWNERSHIP_VIOLATION'
                );
            }
        });

        static::updating(function ($role): void {
            $tenantId = tenant()?->id;

            if (! $tenantId) {
                throw new BusinessException(
                    'A tenant context is required to update a role.',
                    'TENANT_REQUIRED'
                );
            }

            if ((int) $role->getOriginal('tenant_id') !== (int) $tenantId) {
                throw new BusinessException(
                    'Cannot update a role outside the active tenant.',
                    'TENANT_OWNERSHIP_VIOLATION'
                );
            }

            if ($role->isDirty('tenant_id')) {
                throw new BusinessException(
                    'Cannot change the tenant ownership of an existing role.',
                    'TENANT_OWNERSHIP_VIOLATION'
                );
            }
        });
    }
}
