<?php

use Illuminate\Support\Facades\Route;
use App\Modules\CustomerReceipt\Controllers\CustomerReceiptController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {

    Route::post(
        'customer-receipts/{customerReceipt}/confirm',
        [CustomerReceiptController::class, 'confirm']
    )->name('customer-receipts.confirm');

    Route::post(
        'customer-receipts/{customerReceipt}/cancel',
        [CustomerReceiptController::class, 'cancel']
    )->name('customer-receipts.cancel');

    Route::apiResource(
        'customer-receipts',
        CustomerReceiptController::class
    );
});
