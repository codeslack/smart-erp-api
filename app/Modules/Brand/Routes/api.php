<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Brand\Controllers\BrandController;

Route::middleware([
    'auth:sanctum',
    'tenant',
    'permission.tenant',
])->apiResource(
    'brands',
    BrandController::class
);
