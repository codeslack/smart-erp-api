<?php

use Illuminate\Support\Facades\Route;

use App\Modules\Product\Controllers\ProductController;
use App\Modules\Product\Controllers\ProductVariantController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {

    Route::apiResource(
        'products',
        ProductController::class
    );

    Route::apiResource(
        'product-variants',
        ProductVariantController::class
    );
});
