<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Inventory\Controllers\InventoryController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->prefix('inventory')->group(function () {

    Route::post(
        'opening-stock',
        [InventoryController::class, 'openingStock']
    );

    Route::get(
        'products/{product}/stock',
        [InventoryController::class, 'stock']
    );

    Route::get(
        'products/{product}/ledger',
        [InventoryController::class, 'ledger']
    );
});
