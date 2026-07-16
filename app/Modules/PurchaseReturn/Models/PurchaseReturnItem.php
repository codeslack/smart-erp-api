<?php

namespace App\Modules\PurchaseReturn\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Product\Models\Product;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Purchase\Models\PurchaseItem;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnCondition;

class PurchaseReturnItem extends Model
{
    protected $fillable = [
        'purchase_return_id',

        'purchase_item_id',

        'product_id',
        'warehouse_id',

        'quantity',
        'unit_cost',

        'discount',
        'tax',

        'line_total',

        'condition',
        'reason',
    ];

    protected $casts = [
        'quantity'   => 'decimal:4',
        'unit_cost'  => 'decimal:4',

        'discount'   => 'decimal:4',
        'tax'        => 'decimal:4',

        'line_total' => 'decimal:4',

        'condition'  => PurchaseReturnCondition::class,
    ];

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(
            PurchaseReturn::class
        );
    }

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(
            PurchaseItem::class
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class
        )->withDefault();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(
            Warehouse::class
        )->withDefault();
    }
}