<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Payment\Controllers\PaymentController;

/*
|--------------------------------------------------------------------------
| Payment Custom Actions
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'tenant',
    'permission.tenant',
])->group(function () {

    Route::post(
        'payments/{payment}/confirm',
        [PaymentController::class, 'confirm']
    )
        ->middleware('permission.auto:payment.confirm')
        ->name('payments.confirm');

    Route::post(
        'payments/{payment}/cancel',
        [PaymentController::class, 'cancel']
    )
        ->middleware('permission.auto:payment.cancel')
        ->name('payments.cancel');

    Route::post(
        'payments/{payment}/reverse',
        [PaymentController::class, 'reverse']
    )
        ->middleware('permission.auto:payment.reverse')
        ->name('payments.reverse');
});

/*
|--------------------------------------------------------------------------
| Payment CRUD
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'tenant',
    'permission.tenant',
    'permission.auto',
])->group(function () {

    Route::apiResource(
        'payments',
        PaymentController::class
    );
});
