<?php

namespace App\Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Product\Models\Product;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',

        'product_id',

        'warehouse_id',

        'quantity',

        'unit_price',
        
        'cost_price',

        'line_total',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(
            Sale::class
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class
        );
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(
            Warehouse::class
        );
    }
}