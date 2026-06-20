<?php

namespace App\Modules\PurchaseOrder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'warehouse_id',
        'quantity',
        'unit_cost',
        'line_total',
        'received_quantity',
        'pending_quantity',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(
            PurchaseOrder::class
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
