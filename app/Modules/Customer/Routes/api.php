<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Customer\Controllers\CustomerController;

Route::middleware([
    'auth:sanctum',
    'tenant',
    'permission.tenant',
    'permission.auto',
])->group(function () {

    Route::apiResource(
        'customers',
        CustomerController::class
    );
});
