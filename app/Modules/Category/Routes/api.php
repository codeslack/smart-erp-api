<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Category\Controllers\CategoryController;

Route::middleware([
    'auth:sanctum',
    'tenant',
    'permission.tenant',
    'permission.auto',
])->group(function () {

    Route::apiResource(
        'categories',
        CategoryController::class
    );
});
