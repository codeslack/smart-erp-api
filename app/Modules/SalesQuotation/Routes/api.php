<?php

use App\Modules\SalesQuotation\Controllers\SalesQuotationController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {
    Route::post(
        'sales-quotations/{uuid}/send',
        [SalesQuotationController::class, 'send']
    )->name('sales-quotations.send');

    Route::post(
        'sales-quotations/{uuid}/accept',
        [SalesQuotationController::class, 'accept']
    )->name('sales-quotations.accept');

    Route::post(
        'sales-quotations/{uuid}/approve',
        [SalesQuotationController::class, 'approve']
    )->name('sales-quotations.approve');

    Route::post(
        'sales-quotations/{uuid}/reject',
        [SalesQuotationController::class, 'reject']
    )->name('sales-quotations.reject');

    Route::post(
        'sales-quotations/{uuid}/cancel',
        [SalesQuotationController::class, 'cancel']
    )->name('sales-quotations.cancel');

    Route::post(
        'sales-quotations/{uuid}/convert-to-order',
        [SalesQuotationController::class, 'convertToOrder']
    )->name('sales-quotations.convert-to-order');

    Route::post(
        'sales-quotations/{uuid}/convert-to-sales-order',
        [SalesQuotationController::class, 'convertToSalesOrder']
    )->name('sales-quotations.convert-to-sales-order');

    Route::get(
        'sales-quotations/{uuid}/items',
        [SalesQuotationController::class, 'items']
    )->name('sales-quotations.items');

    Route::apiResource(
        'sales-quotations',
        SalesQuotationController::class
    )->parameters([
        'sales-quotations' => 'uuid',
    ]);
});
