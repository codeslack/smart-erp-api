<?php

use Illuminate\Support\Facades\Route;
use App\Modules\SalesOrder\Controllers\SalesOrderController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {

    Route::post(
        'sales-orders/{salesOrder}/approve',
        [SalesOrderController::class, 'approve']
    )->name('sales-orders.approve');

    Route::post(
        'sales-orders/{salesOrder}/convert-to-delivery-note',
        [SalesOrderController::class, 'convertToDeliveryNote']
    )->name('sales-orders.convert-to-delivery-note');

    Route::post(
        'sales-orders/{salesOrder}/convert-to-sale',
        [SalesOrderController::class, 'convertToSale']
    )->name('sales-orders.convert-to-sale');


    Route::apiResource(
        'sales-orders',
        SalesOrderController::class
    );
});
