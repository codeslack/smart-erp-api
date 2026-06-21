<?php

namespace App\Modules\DeliveryNote\Models;

use App\Core\Tenant\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryNoteItem extends TenantModel
{
    protected $fillable = [
        'delivery_note_id',
        'product_id',
        'warehouse_id',
        'ordered_quantity',
        'delivered_quantity',
        'pending_quantity',
        'unit_price',
        'line_total',
    ];

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(
            DeliveryNote::class
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            \App\Modules\Product\Models\Product::class
        );
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(
            \App\Modules\Warehouse\Models\Warehouse::class
        );
    }
}
