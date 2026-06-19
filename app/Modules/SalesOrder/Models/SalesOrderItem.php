<?php

namespace App\Modules\SalesOrder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderItem extends Model
{
    protected $fillable = [
        'sales_order_id',
        'product_id',
        'warehouse_id',
        'quantity',
        'unit_price',
        'line_total',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(
            SalesOrder::class
        );
    }
}
