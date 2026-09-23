<?php

use Illuminate\Support\Facades\Route;
use App\Modules\PaymentTerm\Controllers\PaymentTermController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {

    Route::apiResource(
        'payment-terms',
        PaymentTermController::class
    );
});
