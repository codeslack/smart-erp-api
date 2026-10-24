<?php

namespace App\Modules\GoodsReceiptNote\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Core\Models\TenantModel;

class GoodsReceiptNoteItemBatch extends TenantModel
{
    protected $table = 'goods_receipt_note_item_batches';

    protected $fillable = [
        'goods_receipt_note_item_id',
        'batch_no',
        'quantity',
        'expiry_date',
    ];

    protected function casts(): array
    {
        return array_merge(
            parent::casts(),
            [
                'quantity' => 'decimal:4',
                'expiry_date' => 'date',
            ]
        );
    }

    public function goodsReceiptNoteItem(): BelongsTo
    {
        return $this->belongsTo(
            GoodsReceiptNoteItem::class,
            'goods_receipt_note_item_id'
        );
    }
}