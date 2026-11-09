<?php

namespace App\Modules\GoodsReceiptNote;

use App\Core\Modules\Contracts\ModuleInterface;

use App\Modules\GoodsReceiptNote\Providers\GoodsReceiptNoteServiceProvider;

class GoodsReceiptNoteModule implements ModuleInterface
{
    public static function name(): string
    {
        return 'Goods Receipt Note';
    }

    public static function permissions(): array
    {
        return [

            'goods-receipt-note.view',
            'goods-receipt-note.create',
            'goods-receipt-note.update',
            'goods-receipt-note.delete',
            'goods-receipt-note.confirm',
            'goods-receipt-note.cancel',
        ];
    }

    public static function serviceProviders(): array
    {
        return [

            GoodsReceiptNoteServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [];
    }
}