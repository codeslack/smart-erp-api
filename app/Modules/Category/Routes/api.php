<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Category\Controllers\CategoryController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {
    
    Route::apiResource(
        'categories',
        CategoryController::class
    );
});
