<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Area\Controllers\AreaController;

Route::middleware([
    'auth:sanctum',
    'tenant',
    // 'permission.tenant',
])->group(function () {

    Route::apiResource(
        'areas',
        AreaController::class
    );
});
