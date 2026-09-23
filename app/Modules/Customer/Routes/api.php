<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Customer\Controllers\CustomerController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {

    Route::apiResource(
        'customers',
        CustomerController::class
    );
});
