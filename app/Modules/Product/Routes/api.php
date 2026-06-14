<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Product\Controllers\ProductController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->apiResource(
    'products',
    ProductController::class
);
