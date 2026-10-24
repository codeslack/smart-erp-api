<?php

use Illuminate\Support\Facades\Route;
use App\Modules\GoodsReceiptNote\Controllers\GoodsReceiptNoteController;

Route::middleware([
    'auth:sanctum',
    'tenant',
])->group(function () {

    // Route::post(
    //     'goods-receipt-notes/{goodsReceiptNote}/receive',
    //     [GoodsReceiptNoteController::class, 'receive']
    // )->name('goods-receipt-notes.receive');

    // Route::post(
    //     'goods-receipt-notes/{goodsReceiptNote}/convert-to-purchase',
    //     [GoodsReceiptNoteController::class, 'convertToPurchase']
    // )->name('goods-receipt-notes.convert-to-purchase');

    // Route::post(
    //     'goods-receipt-notes/{goodsReceiptNote}/cancel',
    //     [GoodsReceiptNoteController::class, 'cancel']
    // )->name('goods-receipt-notes.cancel');

    // Route::apiResource(
    //     'goods-receipt-notes',
    //     GoodsReceiptNoteController::class
    // );

    Route::prefix('goods-receipt-notes')
        ->name('goods-receipt-notes.')
        ->controller(GoodsReceiptNoteController::class)
        ->group(function () {
            Route::post('/', 'store')
                ->name('store');

            Route::get('/{uuid}', 'show')
                ->name('show')
                ->whereUuid('uuid');

            Route::put('/{uuid}', 'update')
                ->name('update')
                ->whereUuid('uuid');

            Route::post('/{uuid}/confirm', 'confirm')
                ->name('confirm')
                ->whereUuid('uuid');

            Route::post('/{uuid}/cancel', 'cancel')
                ->name('cancel')
                ->whereUuid('uuid');

            Route::put(
                '/{uuid}/items/{itemUuid}',
                'updateItem'
            )
                ->name('items.update')
                ->whereUuid('uuid')
                ->whereUuid('itemUuid');

            Route::delete(
                '/{uuid}/items/{itemUuid}',
                'deleteItem'
            )
                ->name('items.delete')
                ->whereUuid('uuid')
                ->whereUuid('itemUuid');
        });
});
