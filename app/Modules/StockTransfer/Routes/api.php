<?php

use Illuminate\Support\Facades\Route;
use App\Modules\StockTransfer\Controllers\StockTransferController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {

    Route::post(
        'stock-transfers/{stockTransfer}/approve',
        [StockTransferController::class, 'approve']
    )->name('stock-transfers.approve');

    Route::apiResource(
        'stock-transfers',
        StockTransferController::class
    );
});
