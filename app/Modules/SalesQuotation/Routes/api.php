<?php

use App\Modules\SalesQuotation\Controllers\SalesQuotationController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    'tenant',
    'permission.tenant',
])->group(function () {
    Route::post(
        'sales-quotations/{uuid}/send',
        [SalesQuotationController::class, 'send']
    )->middleware('permission.auto:sales-quotation.send')->name('sales-quotations.send');

    Route::post(
        'sales-quotations/{uuid}/accept',
        [SalesQuotationController::class, 'accept']
    )->middleware('permission.auto:sales-quotation.accept')->name('sales-quotations.accept');

    Route::post(
        'sales-quotations/{uuid}/approve',
        [SalesQuotationController::class, 'approve']
    )->middleware('permission.auto:sales-quotation.accept')->name('sales-quotations.approve');

    Route::post(
        'sales-quotations/{uuid}/reject',
        [SalesQuotationController::class, 'reject']
    )->middleware('permission.auto:sales-quotation.reject')->name('sales-quotations.reject');

    Route::post(
        'sales-quotations/{uuid}/cancel',
        [SalesQuotationController::class, 'cancel']
    )->middleware('permission.auto:sales-quotation.cancel')->name('sales-quotations.cancel');

    Route::post(
        'sales-quotations/{uuid}/convert-to-order',
        [SalesQuotationController::class, 'convertToOrder']
    )->middleware('permission.auto:sales-quotation.convert')->name('sales-quotations.convert-to-order');

    Route::post(
        'sales-quotations/{uuid}/convert-to-sales-order',
        [SalesQuotationController::class, 'convertToSalesOrder']
    )->middleware('permission.auto:sales-quotation.convert')->name('sales-quotations.convert-to-sales-order');

    Route::get(
        'sales-quotations/{uuid}/items',
        [SalesQuotationController::class, 'items']
    )->middleware('permission.auto:sales-quotation.view')->name('sales-quotations.items');

    Route::middleware('permission.auto')->group(function () {
        Route::apiResource(
            'sales-quotations',
            SalesQuotationController::class
        )->parameters([
            'sales-quotations' => 'uuid',
        ]);
    });
});
