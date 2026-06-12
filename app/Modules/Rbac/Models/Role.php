<?php

namespace App\Modules\Rbac\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = [
        'tenant_id',
        'name',
        'guard_name',
    ];

    protected static function booted(): void
    {
        static::creating(function ($role) {

            if (
                empty($role->tenant_id)
                && tenant()
            ) {
                $role->tenant_id = tenant()->id;
            }
        });
    }
}