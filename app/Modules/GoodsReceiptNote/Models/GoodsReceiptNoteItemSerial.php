<?php

namespace App\Modules\GoodsReceiptNote\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Core\Models\TenantModel;

class GoodsReceiptNoteItemSerial extends TenantModel
{
    protected $table = 'goods_receipt_note_item_serials';

    protected $fillable = [
        'goods_receipt_note_item_id',
        'serial_number',
    ];

    public function goodsReceiptNoteItem(): BelongsTo
    {
        return $this->belongsTo(
            GoodsReceiptNoteItem::class,
            'goods_receipt_note_item_id'
        );
    }
}