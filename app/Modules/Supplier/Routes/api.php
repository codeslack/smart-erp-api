<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Supplier\Controllers\SupplierController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {

    Route::apiResource(
        'suppliers',
        SupplierController::class
    );
});
