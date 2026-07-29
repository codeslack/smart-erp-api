<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Settings\Controllers\SettingController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->prefix('settings')
    ->group(function () {

        Route::get(
            '{group}',
            [SettingController::class, 'show']
        );

        Route::put(
            '{group}',
            [SettingController::class, 'update']
        );
    });