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
    );

    Route::apiResource(
        'purchases',
        PurchaseController::class
    );

});
