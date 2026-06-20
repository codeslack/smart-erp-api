<?php

namespace App\Modules\GoodsReceiptNote\Models;

use App\Core\Tenant\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptNote extends TenantModel
{
    protected $fillable = [
        'purchase_order_id',
        'supplier_id',
        'grn_no',
        'received_date',
        'grand_total',
        'status',
        'notes',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(
            \App\Modules\PurchaseOrder\Models\PurchaseOrder::class
        );
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(
            \App\Modules\Supplier\Models\Supplier::class
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            GoodsReceiptNoteItem::class
        );
    }
}
