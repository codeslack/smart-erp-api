<?php

use App\Modules\PurchaseReturn\Controllers\PurchaseReturnController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {
    Route::post(
        'purchase-returns/{purchaseReturn}/approve',
        [PurchaseReturnController::class, 'approve']
    )->name('purchase-returns.approve');

    Route::post(
        'purchase-returns/{purchaseReturn}/confirm',
        [PurchaseReturnController::class, 'confirm']
    )->name('purchase-returns.confirm');

    Route::post(
        'purchase-returns/{purchaseReturn}/cancel',
        [PurchaseReturnController::class, 'cancel']
    )->name('purchase-returns.cancel');

    Route::apiResource(
        'purchase-returns',
        PurchaseReturnController::class
    )->parameters(['purchase-returns' => 'purchaseReturn']);
});
