<?php
// filename: app/Modules/Rbac/Routes/api.php

use Illuminate\Support\Facades\Route;
use App\Modules\Rbac\Controllers\RoleController;
use App\Modules\Rbac\Controllers\UserRoleController;
use App\Modules\Rbac\Controllers\RolePermissionController;

Route::middleware([
    'auth:sanctum',
    'tenant',
    'permission.tenant',
])->prefix('rbac')->group(function () {

    Route::apiResource(
        'roles',
        RoleController::class
    );

    Route::get(
        '/permissions',
        [RolePermissionController::class, 'index']
    );

    Route::get(
        '/roles/{role}/permissions',
        [RolePermissionController::class, 'show']
    );

    Route::post(
        '/roles/{role}/permissions',
        [RolePermissionController::class, 'sync']
    );

    Route::get(
        'users/{user}/roles',
        [UserRoleController::class, 'show']
    );

    Route::post(
        'users/{user}/roles',
        [UserRoleController::class, 'sync']
    );
});
