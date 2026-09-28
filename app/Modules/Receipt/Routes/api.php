<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Receipt\Controllers\ReceiptController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {

    Route::post(
        'receipts/{receipt}/confirm',
        [ReceiptController::class, 'confirm']
    )->name('receipts.confirm');

    Route::post(
        'receipts/{receipt}/cancel',
        [ReceiptController::class, 'cancel']
    )->name('receipts.cancel');

    Route::apiResource(
        'receipts',
        ReceiptController::class
    );
});
