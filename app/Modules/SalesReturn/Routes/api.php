<?php

use App\Modules\SalesReturn\Controllers\SalesReturnController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    'tenant',
    'permission.tenant',
])->group(function () {
    Route::post(
        'sales-returns/{uuid}/confirm',
        [SalesReturnController::class, 'confirm']
    )->middleware('permission.auto:sales-return.confirm')->name('sales-returns.confirm');

    Route::post(
        'sales-returns/{uuid}/approve',
        [SalesReturnController::class, 'approve']
    )->middleware('permission.auto:sales-return.approve')->name('sales-returns.approve');

    Route::post(
        'sales-returns/{uuid}/cancel',
        [SalesReturnController::class, 'cancel']
    )->middleware('permission.auto:sales-return.cancel')->name('sales-returns.cancel');

    Route::get(
        'sales-returns/{uuid}/items',
        [SalesReturnController::class, 'items']
    )->middleware('permission.auto:sales-return.view')->name('sales-returns.items');

    Route::middleware('permission.auto')->group(function () {
        Route::apiResource(
            'sales-returns',
            SalesReturnController::class
        )->parameters([
            'sales-returns' => 'uuid',
        ]);
    });
});
