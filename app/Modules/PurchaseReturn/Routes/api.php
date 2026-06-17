<?php

use Illuminate\Support\Facades\Route;
use App\Modules\PurchaseReturn\Controllers\PurchaseReturnController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {

    Route::post(
        'purchase-returns/{purchaseReturn}/approve',
        [PurchaseReturnController::class, 'approve']
    )->name('purchase-returns.approve');

    Route::apiResource(
        'purchase-returns',
        PurchaseReturnController::class
    );
});
