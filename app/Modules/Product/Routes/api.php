<?php

use Illuminate\Support\Facades\Route;

use App\Modules\Product\Controllers\ProductController;
use App\Modules\Product\Controllers\ProductVariantController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Products
    |--------------------------------------------------------------------------
    */

    Route::apiResource(
        'products',
        ProductController::class
    );

    /*
    |--------------------------------------------------------------------------
    | Product Variants (Nested)
    |--------------------------------------------------------------------------
    */

    Route::get(
        'products/{product}/variants',
        [ProductVariantController::class, 'index']
    )->name('products.variants.index');

    Route::post(
        'products/{product}/variants',
        [ProductVariantController::class, 'store']
    )->name('products.variants.store');

    /*
    |--------------------------------------------------------------------------
    | Variant Management
    |--------------------------------------------------------------------------
    */

    Route::get(
        'product-variants/{productVariant}',
        [ProductVariantController::class, 'show']
    )->name('product-variants.show');

    Route::put(
        'product-variants/{productVariant}',
        [ProductVariantController::class, 'update']
    )->name('product-variants.update');

    Route::patch(
        'product-variants/{productVariant}',
        [ProductVariantController::class, 'update']
    )->name('product-variants.patch');

    Route::delete(
        'product-variants/{productVariant}',
        [ProductVariantController::class, 'destroy']
    )->name('product-variants.destroy');
});