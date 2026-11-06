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
])->prefix('users')->name('users.')->group(function () {

    Route::get(
        '/',
        [UserController::class, 'index']
    )->middleware('permission:user.view')
        ->name('index');

    Route::post(
        '/',
        [UserController::class, 'store']
    )->middleware('permission:user.create')
        ->name('store');

    Route::get(
        '/{user}',
        [UserController::class, 'show']
    )->middleware('permission:user.view')
        ->name('show');

    Route::put(
        '/{user}',
        [UserController::class, 'update']
    )->middleware('permission:user.update')
        ->name('update');

    Route::patch(
        '/{user}',
        [UserController::class, 'update']
    )->middleware('permission:user.update')
        ->name('update.patch');

    Route::delete(
        '/{user}',
        [UserController::class, 'destroy']
    )->middleware('permission:user.delete')
        ->name('destroy');
});
