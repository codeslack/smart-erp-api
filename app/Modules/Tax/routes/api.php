<?php

use Illuminate\Support\Facades\Route;

use App\Modules\Tax\Controllers\TaxController;
use App\Modules\Tax\Controllers\TaxComponentController;
use App\Modules\Tax\Controllers\TaxGroupController;
use App\Modules\Tax\Controllers\TaxGroupComponentController;
use App\Modules\Tax\Controllers\TaxApplicabilityRuleController;
use App\Modules\Tax\Controllers\TaxAccountingMappingController;
use App\Modules\Tax\Controllers\TaxRegistrationController;

Route::middleware([
    'auth:sanctum',
    'tenant',
    'permission.tenant',
    'permission.auto',
])->group(function () {

    Route::apiResource(
        'taxes',
        TaxController::class
    );

    Route::apiResource(
        'tax-components',
        TaxComponentController::class
    );

    Route::apiResource(
        'tax-groups',
        TaxGroupController::class
    );

    Route::apiResource(
        'tax-group-components',
        TaxGroupComponentController::class
    );

    Route::apiResource(
        'tax-applicability-rules',
        TaxApplicabilityRuleController::class
    );

    Route::apiResource(
        'tax-accounting-mappings',
        TaxAccountingMappingController::class
    );

    Route::apiResource(
        'tax-registrations',
        TaxRegistrationController::class
    );
});