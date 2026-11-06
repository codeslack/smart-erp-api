<?php

namespace App\Modules\Rbac\Models;

use App\Core\Exceptions\BusinessException;
use App\Core\Traits\HasUuidPrimaryKey;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'tenant_id',
        'name',
        'guard_name',
    ];

    protected static function booted(): void
    {
        static::creating(function ($role) {
            $tenantId = tenant()?->id;

            if (! $tenantId) {
                throw new BusinessException(
                    'A tenant context is required to create a role.'
                );
            }

            if (! $role->tenant_id) {
                $role->tenant_id = $tenantId;
            }

            if ((int) $role->tenant_id !== (int) $tenantId) {
                throw new BusinessException(
                    'Cannot create a role for another tenant.'
                );
            }
        });
    }
}
