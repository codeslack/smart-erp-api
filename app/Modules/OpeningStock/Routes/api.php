<?php

use Illuminate\Support\Facades\Route;

use App\Modules\OpeningStock\Controllers\OpeningStockController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {

    Route::apiResource(
        'opening-stocks',
        OpeningStockController::class
    );

    Route::post( 
        'opening-stocks/{openingStock}/approve', 
        [OpeningStockController::class, 'approve'] 
    )->name('opening-stocks.approve');
});
