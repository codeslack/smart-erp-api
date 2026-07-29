<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Product\Controllers\ProductController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {

    Route::get(
        'products/search',
        [ProductController::class, 'search']
    )->name(
        'products.search'
    );

    Route::apiResource(
        'products',
        ProductController::class
    );
});
