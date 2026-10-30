<?php

use App\Modules\SalesOrder\Controllers\SalesOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {
    Route::post(
        'sales-orders/{uuid}/confirm',
        [SalesOrderController::class, 'confirm']
    )->name('sales-orders.confirm');

    Route::post(
        'sales-orders/{uuid}/approve',
        [SalesOrderController::class, 'approve']
    )->name('sales-orders.approve');

    Route::post(
        'sales-orders/{uuid}/cancel',
        [SalesOrderController::class, 'cancel']
    )->name('sales-orders.cancel');

    Route::get(
        'sales-orders/{uuid}/items',
        [SalesOrderController::class, 'items']
    )->name('sales-orders.items');

    Route::apiResource(
        'sales-orders',
        SalesOrderController::class
    )->parameters([
        'sales-orders' => 'uuid',
    ]);
});
