<?php

namespace App\Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Product\Models\Product;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\PurchaseReturn\Models\PurchaseReturnItem;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnStatus;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id',

        'product_id',
        'warehouse_id',

        'quantity',
        'unit_cost',

        'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'line_total' => 'decimal:4',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(
            Purchase::class
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

    public function returnItems(): HasMany
    {
        return $this->hasMany(
            PurchaseReturnItem::class
        );
    }

    public function getReturnedQuantityAttribute(): float
    {
        return (float) $this->returnItems()
            ->whereHas(
                'purchaseReturn',
                fn ($q) => $q->where(
                    'status',
                    PurchaseReturnStatus::CONFIRMED
                )
            )
            ->sum('returned_quantity');
    }

    public function getAvailableReturnQuantityAttribute(): float
    {
        return max(
            0,
            (float) $this->quantity -
            (float) $this->returned_quantity
        );
    }
    
    public function getLineCostAttribute(): float
    {
        return (float)
            $this->returned_quantity
            *
            $this->unit_cost;
    }    
}