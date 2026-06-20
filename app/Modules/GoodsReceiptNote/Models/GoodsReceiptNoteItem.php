<?php

namespace App\Modules\GoodsReceiptNote\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptNoteItem extends Model
{
    protected $fillable = [
        'goods_receipt_note_id',
        'product_id',
        'warehouse_id',
        'ordered_quantity',
        'received_quantity',
        'pending_quantity',
        'unit_cost',
        'line_total',
    ];

    public function goodsReceiptNote(): BelongsTo
    {
        return $this->belongsTo(
            GoodsReceiptNote::class
        );
    }
}
