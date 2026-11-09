<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Supplier\Controllers\SupplierController;

Route::middleware([
    'auth:sanctum',
    'tenant',
    'permission.tenant',
    'permission.auto',
])->group(function () {

    Route::apiResource(
        'suppliers',
        SupplierController::class
    );
});
