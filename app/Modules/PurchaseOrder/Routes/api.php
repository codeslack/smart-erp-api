<?php

use Illuminate\Support\Facades\Route;
use App\Modules\PurchaseOrder\Controllers\PurchaseOrderController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {

    Route::post(
        'purchase-orders/{purchaseOrder}/approve',
        [PurchaseOrderController::class, 'approve']
    )->name('purchase-orders.approve');

    Route::post(
        'purchase-orders/{purchaseOrder}/convert',
        [PurchaseOrderController::class, 'convertToPurchase']
    )->name('purchase-orders.convert');

    Route::apiResource(
        'purchase-orders',
        PurchaseOrderController::class
    );
});
