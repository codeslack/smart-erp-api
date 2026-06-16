<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Sales\Controllers\SaleController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {

    Route::post(
        'sales/{sale}/approve',
        [SaleController::class, 'approve']
    )->name('sales.approve');

    Route::apiResource(
        'sales',
        SaleController::class
    );

});