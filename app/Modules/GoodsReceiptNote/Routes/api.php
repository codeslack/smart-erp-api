<?php

use Illuminate\Support\Facades\Route;
use App\Modules\GoodsReceiptNote\Controllers\GoodsReceiptNoteController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {

    Route::post(
        'goods-receipt-notes/{goodsReceiptNote}/receive',
        [GoodsReceiptNoteController::class, 'receive']
    )->name('goods-receipt-notes.receive');

    Route::post(
        'goods-receipt-notes/{goodsReceiptNote}/convert-to-purchase',
        [GoodsReceiptNoteController::class, 'convertToPurchase']
    )->name('goods-receipt-notes.convert-to-purchase');

    Route::post(
        'goods-receipt-notes/{goodsReceiptNote}/cancel',
        [GoodsReceiptNoteController::class, 'cancel']
    )->name('goods-receipt-notes.cancel');

    Route::apiResource(
        'goods-receipt-notes',
        GoodsReceiptNoteController::class
    );
});
