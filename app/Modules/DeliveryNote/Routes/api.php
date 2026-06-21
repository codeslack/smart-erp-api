<?php

use Illuminate\Support\Facades\Route;
use App\Modules\DeliveryNote\Controllers\DeliveryNoteController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {

    Route::post(
        'delivery-notes/{deliveryNote}/deliver',
        [DeliveryNoteController::class, 'deliver']
    )->name(
        'delivery-notes.deliver'
    );

    Route::post(
        'delivery-notes/{deliveryNote}/convert-to-sale',
        [DeliveryNoteController::class, 'convertToSale']
    )->name(
        'delivery-notes.convert-to-sale'
    );

    Route::post(
        'delivery-notes/{deliveryNote}/cancel',
        [DeliveryNoteController::class, 'cancel']
    )->name(
        'delivery-notes.cancel'
    );

    Route::apiResource(
        'delivery-notes',
        DeliveryNoteController::class
    );
});
