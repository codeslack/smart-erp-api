<?php

namespace App\Modules\SalesReturn\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\SalesReturn\Enums\SalesReturnCondition;

class SalesReturnItem extends Model
{
    protected $fillable = [
        'sales_return_id',

        'sale_item_id',

        'product_id',
        'warehouse_id',

        'quantity',
        'unit_price',

        'discount',
        'tax',

        'line_total',

        'condition',
        'reason',
    ];

    protected $casts = [
        'quantity'   => 'decimal:4',
        'unit_price' => 'decimal:4',

        'discount'   => 'decimal:4',
        'tax'        => 'decimal:4',

        'line_total' => 'decimal:4',

        'condition'  => SalesReturnCondition::class,
    ];

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(
            SalesReturn::class
        );
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(
            \App\Modules\Sales\Models\SaleItem::class
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

    public function sale(): BelongsTo
    {
        return $this->salesReturn()
            ->withDefault();
    }
}
