<?php

use Illuminate\Support\Facades\Route;
use App\Modules\StockAdjustment\Controllers\StockAdjustmentController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {

    Route::post(
        'stock-adjustments/{stockAdjustment}/approve',
        [StockAdjustmentController::class, 'approve']
    )->name('stock-adjustments.approve');

    Route::apiResource(
        'stock-adjustments',
        StockAdjustmentController::class
    );
});
