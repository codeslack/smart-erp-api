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

            'goods_receipt_note.view',
            'goods_receipt_note.create',
            'goods_receipt_note.update',
            'goods_receipt_note.delete',
            'goods_receipt_note.confirm',
            'goods_receipt_note.cancel',
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