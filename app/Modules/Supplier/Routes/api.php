<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Supplier\Controllers\SupplierController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->apiResource(
    'suppliers',
    SupplierController::class
);
