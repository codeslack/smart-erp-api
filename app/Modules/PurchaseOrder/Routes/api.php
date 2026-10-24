<?php

use Illuminate\Support\Facades\Route;
use App\Modules\PurchaseOrder\Controllers\PurchaseOrderController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {

    Route::apiResource(
        'purchase-orders',
        PurchaseOrderController::class
    );

    Route::post(
        'purchase-orders/{uuid}/confirm',
        [PurchaseOrderController::class, 'confirm']
    )->name('purchase-orders.confirm');

    Route::post(
        'purchase-orders/{uuid}/cancel',
        [PurchaseOrderController::class, 'cancel']
    )->name('purchase-orders.cancel');

    Route::get(
        'purchase-orders/{uuid}/items',
        [PurchaseOrderController::class, 'items']
    )->name('purchase-orders.items');
});
