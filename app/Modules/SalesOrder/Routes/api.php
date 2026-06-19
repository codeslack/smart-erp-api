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
        'sales-orders/{salesOrder}/convert',
        [SalesOrderController::class, 'convertToSale']
    )->name('sales-orders.convert');

    Route::apiResource(
        'sales-orders',
        SalesOrderController::class
    );

});
