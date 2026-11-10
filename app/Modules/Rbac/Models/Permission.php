<?php

namespace App\Modules\Rbac\Models;

use App\Core\Traits\HasUuidPrimaryKey;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'name',
        'guard_name',
    ];
}
