<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Warehouse\Controllers\WarehouseController;

Route::middleware([
    'auth:sanctum',
    'tenant',
    'permission.tenant',
])->apiResource(
    'warehouses',
    WarehouseController::class
);
