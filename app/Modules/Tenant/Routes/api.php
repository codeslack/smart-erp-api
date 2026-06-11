<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Tenant\Controllers\TenantController;

Route::apiResource(
    'tenants',
    TenantController::class
);