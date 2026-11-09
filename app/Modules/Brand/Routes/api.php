<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Brand\Controllers\BrandController;

Route::middleware([
    'auth:sanctum',
    'tenant',
    'permission.tenant',
    'permission.auto',
])->group(function () {

    Route::apiResource(
        'brands',
        BrandController::class
    );
});
