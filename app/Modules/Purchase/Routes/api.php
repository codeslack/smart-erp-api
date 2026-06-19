<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Purchase\Controllers\PurchaseController;


Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {

    Route::post(
        'purchases/{purchase}/approve',
        [PurchaseController::class, 'approve']
    )->name('purchases.approve');

    Route::apiResource(
        'purchases',
        PurchaseController::class
    );

});
