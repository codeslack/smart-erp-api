<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Purchase\Controllers\PurchaseController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {

    Route::post(
        'purchases/{purchase}/confirm',
        [PurchaseController::class, 'confirm']
    )->name('purchases.confirm');

    Route::post(
        'purchases/{purchase}/cancel',
        [PurchaseController::class, 'cancel']
    )->name('purchases.cancel');

    Route::apiResource(
        'purchases',
        PurchaseController::class
    );
});
