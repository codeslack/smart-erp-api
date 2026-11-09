<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Unit\Controllers\UnitController;

Route::middleware([
    'auth:sanctum',
    'tenant',
    'permission.tenant',
    'permission.auto',
])->group(function () {

    Route::apiResource(
        'units',
        UnitController::class
    );
});