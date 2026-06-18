<?php

namespace App\Modules\SalesReturn\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function salesReturn()
    {
        return $this->belongsTo(
            SalesReturn::class
        );
    }

    public function product()
    {
        return $this->belongsTo(
            \App\Modules\Product\Models\Product::class
        );
    }

    public function warehouse()
    {
        return $this->belongsTo(
            \App\Modules\Warehouse\Models\Warehouse::class
        );
    }
}
