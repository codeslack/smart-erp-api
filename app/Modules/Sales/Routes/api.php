<?php

use App\Modules\Sales\Controllers\SaleController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {
    Route::post(
        'sales/{uuid}/confirm',
        [SaleController::class, 'confirm']
    )->name('sales.confirm');

    Route::post(
        'sales/{uuid}/approve',
        [SaleController::class, 'approve']
    )->name('sales.approve');

    Route::post(
        'sales/{uuid}/cancel',
        [SaleController::class, 'cancel']
    )->name('sales.cancel');

    Route::get(
        'sales/{uuid}/items',
        [SaleController::class, 'items']
    )->name('sales.items');

    Route::apiResource(
        'sales',
        SaleController::class
    )->parameters([
        'sales' => 'uuid',
    ]);
});
