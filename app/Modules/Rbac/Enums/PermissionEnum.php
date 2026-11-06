<?php

namespace App\Modules\Rbac\Enums;

enum PermissionEnum: string
{
    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    */

    case USER_VIEW = 'user.view';
    case USER_CREATE = 'user.create';
    case USER_UPDATE = 'user.update';
    case USER_DELETE = 'user.delete';

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    */

    case ROLE_VIEW = 'role.view';
    case ROLE_CREATE = 'role.create';
    case ROLE_UPDATE = 'role.update';
    case ROLE_DELETE = 'role.delete';
    case ROLE_ASSIGN = 'role.assign';

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */

    case PERMISSION_VIEW = 'permission.view';
    case PERMISSION_ASSIGN = 'permission.assign';

    /*
    |--------------------------------------------------------------------------
    | Tenants
    |--------------------------------------------------------------------------
    */

    case TENANT_VIEW = 'tenant.view';
    case TENANT_CREATE = 'tenant.create';
    case TENANT_UPDATE = 'tenant.update';
    case TENANT_DELETE = 'tenant.delete';

    public static function values(): array
    {
        return array_map(
            fn (self $permission) => $permission->value,
            self::cases()
        );
    }
}