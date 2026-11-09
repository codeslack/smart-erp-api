<?php

use Illuminate\Support\Facades\Route;

use App\Modules\ProductUnit\Controllers\ProductUnitController;

Route::middleware([
    'auth:sanctum',
    'tenant',
    'permission.tenant',
    'permission.auto',
])
    ->prefix('product-units')
    ->group(function () {

        Route::get(
            '/',
            [ProductUnitController::class, 'index']
        )->name('product-units.index');

        Route::post(
            '/',
            [ProductUnitController::class, 'store']
        )->name('product-units.store');

        Route::get(
            '/{productUnit}',
            [ProductUnitController::class, 'show']
        )->name('product-units.show');

        Route::put(
            '/{productUnit}',
            [ProductUnitController::class, 'update']
        )->name('product-units.update');

        Route::delete(
            '/{productUnit}',
            [ProductUnitController::class, 'destroy']
        )->name('product-units.destroy');
    });
