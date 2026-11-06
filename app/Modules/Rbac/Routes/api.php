<?php

use Illuminate\Support\Facades\Route;

use App\Modules\Rbac\Controllers\RoleController;
use App\Modules\Rbac\Controllers\UserRoleController;
use App\Modules\Rbac\Controllers\RolePermissionController;

Route::middleware([
    'auth:sanctum',
    'tenant',
    'permission.tenant',
])->prefix('rbac')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    */

    Route::get('roles', [
        RoleController::class,
        'index',
    ])->middleware('permission:role.view');

    Route::post('roles', [
        RoleController::class,
        'store',
    ])->middleware('permission:role.create');

    Route::get('roles/{uuid}', [
        RoleController::class,
        'show',
    ])->middleware('permission:role.view');

    Route::put('roles/{uuid}', [
        RoleController::class,
        'update',
    ])->middleware('permission:role.update');

    Route::delete('roles/{uuid}', [
        RoleController::class,
        'destroy',
    ])->middleware('permission:role.delete');

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */

    Route::get('permissions', [
        RolePermissionController::class,
        'index',
    ])->middleware('permission:permission.view');

    Route::get('roles/{uuid}/permissions', [
        RolePermissionController::class,
        'show',
    ])->middleware([
        'permission:role.view',
        'permission:permission.view',
    ]);

    Route::put('roles/{uuid}/permissions', [
        RolePermissionController::class,
        'sync',
    ])->middleware('permission:permission.assign');

    /*
    |--------------------------------------------------------------------------
    | User Roles
    |--------------------------------------------------------------------------
    */

    Route::get('users/{userUuid}/roles', [
        UserRoleController::class,
        'show',
    ])->middleware('permission:user.view');

    Route::put('users/{userUuid}/roles', [
        UserRoleController::class,
        'sync',
    ])->middleware('permission:role.assign');

});