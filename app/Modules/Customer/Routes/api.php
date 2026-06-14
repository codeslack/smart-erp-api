<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Customer\Controllers\CustomerController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->apiResource(
    'customers',
    CustomerController::class
);
