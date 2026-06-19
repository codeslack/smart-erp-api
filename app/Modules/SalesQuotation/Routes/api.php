<?php

use Illuminate\Support\Facades\Route;
use App\Modules\SalesQuotation\Controllers\SalesQuotationController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {

    Route::post(
        'sales-quotations/{salesQuotation}/approve',
        [SalesQuotationController::class, 'approve']
    )->name('sales-quotations.approve');

    Route::post(
        'sales-quotations/{salesQuotation}/convert',
        [SalesQuotationController::class, 'convertToSale']
    )->name('sales-quotations.convert');
        
    Route::apiResource(
        'sales-quotations',
        SalesQuotationController::class
    );

});
