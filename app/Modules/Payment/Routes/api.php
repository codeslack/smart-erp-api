<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Payment\Controllers\PaymentController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {

    Route::post(
        'payments/{payment}/confirm',
        [PaymentController::class, 'confirm']
    )->name('payments.confirm');

    Route::post(
        'payments/{payment}/cancel',
        [PaymentController::class, 'cancel']
    )->name('payments.cancel');

    Route::apiResource(
        'payments',
        PaymentController::class
    );
});
