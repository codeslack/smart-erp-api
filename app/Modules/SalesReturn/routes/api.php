<?php

use Illuminate\Support\Facades\Route;
use App\Modules\SalesReturn\Controllers\SalesReturnController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {

    Route::post(
        'sales-returns/{salesReturn}/approve',
        [SalesReturnController::class, 'approve']
    )->name('sales-returns.approve');

    Route::apiResource(
        'sales-returns',
        SalesReturnController::class
    );
});
