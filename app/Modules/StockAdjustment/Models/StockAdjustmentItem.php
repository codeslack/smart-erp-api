<?php

namespace App\Modules\StockAdjustment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentItem extends Model
{
    protected $fillable = [
        'stock_adjustment_id',

        'product_id',

        'warehouse_id',

        'system_quantity',

        'physical_quantity',

        'adjustment_quantity',

        'remarks',
    ];

    public function stockAdjustment(): BelongsTo
    {
        return $this->belongsTo(
            StockAdjustment::class
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
