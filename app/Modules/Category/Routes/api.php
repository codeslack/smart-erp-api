<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Category\Controllers\CategoryController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->apiResource(
    'categories',
    CategoryController::class
);
