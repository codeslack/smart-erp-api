<?php

use Illuminate\Support\Facades\Route;

use App\Modules\User\Controllers\AuthController;
use App\Modules\User\Controllers\UserController;

Route::prefix('auth')->name('auth.')->group(function () {

    Route::post(
        '/login',
        [AuthController::class, 'login']
    )->name('login');

    Route::middleware([
        'auth:sanctum',
        'tenant',
    ])->group(function () {

        Route::get(
            '/me',
            [AuthController::class, 'me']
        )->name('me');

        Route::post(
            '/logout',
            [AuthController::class, 'logout']
        )->name('logout');
    });
});

Route::middleware([
    'auth:sanctum',
    'tenant',
    'permission.tenant',
    'permission.auto',
])->group(function () {

    Route::apiResource('users', UserController::class)
        ->names('users');
});
