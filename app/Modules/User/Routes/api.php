<?php

use Illuminate\Support\Facades\Route;
use App\Modules\User\Controllers\UserController;
use App\Modules\User\Controllers\AuthController;


Route::prefix('auth')
    ->group(function () {

        Route::post(
            '/login',
            [AuthController::class, 'login']
        );

        Route::middleware('auth:sanctum')
            ->group(function () {

                Route::get(
                    '/me',
                    [AuthController::class, 'me']
                );

                Route::post(
                    '/logout',
                    [AuthController::class, 'logout']
                );
            });
    });

Route::middleware('tenant')
    ->group(function () {

        Route::apiResource(
            'users',
            UserController::class
        );
    });
