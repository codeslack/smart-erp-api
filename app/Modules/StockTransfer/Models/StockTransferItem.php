<?php

namespace App\Modules\StockTransfer\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransferItem extends Model
{
    protected $fillable = [
        'stock_transfer_id',
        'product_id',
        'quantity',
    ];

    public function stockTransfer()
    {
        return $this->belongsTo(
            StockTransfer::class
        );
    }

    public function product()
    {
        return $this->belongsTo(
            \App\Modules\Product\Models\Product::class
        );
    }
}
