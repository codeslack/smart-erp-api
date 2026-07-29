<?php

namespace App\Modules\Rbac\Enums;

enum PermissionEnum: string
{
    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    */

    case USERS_VIEW = 'users.view';

    case USERS_CREATE = 'users.create';

    case USERS_UPDATE = 'users.update';

    case USERS_DELETE = 'users.delete';

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    */

    case ROLES_VIEW = 'roles.view';

    case ROLES_CREATE = 'roles.create';

    case ROLES_UPDATE = 'roles.update';

    case ROLES_DELETE = 'roles.delete';

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */

    case PERMISSIONS_VIEW = 'permissions.view';

    case PERMISSIONS_ASSIGN = 'permissions.assign';

    /*
    |--------------------------------------------------------------------------
    | Tenants
    |--------------------------------------------------------------------------
    */

    case TENANTS_VIEW = 'tenants.view';

    case TENANTS_CREATE = 'tenants.create';

    case TENANTS_UPDATE = 'tenants.update';

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    case DASHBOARD_VIEW = 'dashboard.view';

    public static function values(): array
    {
        return array_map(
            fn (self $permission) => $permission->value,
            self::cases()
        );
    }
}
