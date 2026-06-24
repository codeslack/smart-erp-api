<?php

use Illuminate\Support\Facades\Route;
use App\Modules\SupplierPayment\Controllers\SupplierPaymentController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {

    Route::post(
        'supplier-payments/{supplierPayment}/confirm',
        [SupplierPaymentController::class, 'confirm']
    )->name('supplier-payments.confirm');

    Route::post(
        'supplier-payments/{supplierPayment}/cancel',
        [SupplierPaymentController::class, 'cancel']
    )->name('supplier-payments.cancel');

    Route::apiResource(
        'supplier-payments',
        SupplierPaymentController::class
    );
});