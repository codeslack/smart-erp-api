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
});
