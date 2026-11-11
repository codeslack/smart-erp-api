<?php

use App\Modules\SalesOrder\Controllers\SalesOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    'tenant',
    'permission.tenant',
])->group(function () {
    Route::post(
        'sales-orders/{uuid}/confirm',
        [SalesOrderController::class, 'confirm']
    )->middleware('permission.auto:sales-order.confirm')->name('sales-orders.confirm');

    Route::post(
        'sales-orders/{uuid}/approve',
        [SalesOrderController::class, 'approve']
    )->middleware('permission.auto:sales-order.confirm')->name('sales-orders.approve');

    Route::post(
        'sales-orders/{uuid}/cancel',
        [SalesOrderController::class, 'cancel']
    )->middleware('permission.auto:sales-order.cancel')->name('sales-orders.cancel');

    Route::get(
        'sales-orders/{uuid}/items',
        [SalesOrderController::class, 'items']
    )->middleware('permission.auto:sales-order.view')->name('sales-orders.items');

    Route::middleware('permission.auto')->group(function () {
        Route::apiResource(
            'sales-orders',
            SalesOrderController::class
        )->parameters([
            'sales-orders' => 'uuid',
        ]);
    });
});
