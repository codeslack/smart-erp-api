<?php

namespace App\Modules\SalesReturn\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnItem extends Model
{
    protected $fillable = [
        'sales_return_id',
        'product_id',
        'warehouse_id',
        'quantity',
        'unit_price',
        'line_total',
    ];

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(
            SalesReturn::class
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
