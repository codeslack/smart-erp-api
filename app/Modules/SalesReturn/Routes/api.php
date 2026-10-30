<?php

use App\Modules\SalesReturn\Controllers\SalesReturnController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {
    Route::post(
        'sales-returns/{uuid}/confirm',
        [SalesReturnController::class, 'confirm']
    )->name('sales-returns.confirm');

    Route::post(
        'sales-returns/{uuid}/approve',
        [SalesReturnController::class, 'approve']
    )->name('sales-returns.approve');

    Route::post(
        'sales-returns/{uuid}/cancel',
        [SalesReturnController::class, 'cancel']
    )->name('sales-returns.cancel');

    Route::get(
        'sales-returns/{uuid}/items',
        [SalesReturnController::class, 'items']
    )->name('sales-returns.items');

    Route::apiResource(
        'sales-returns',
        SalesReturnController::class
    )->parameters([
        'sales-returns' => 'uuid',
    ]);
});
