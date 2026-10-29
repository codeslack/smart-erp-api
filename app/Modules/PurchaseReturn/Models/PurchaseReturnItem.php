<?php

namespace App\Modules\PurchaseReturn\Models;

use App\Core\Models\TenantModel;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\Purchase\Models\PurchaseItem;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnCondition;
use App\Modules\Unit\Models\Unit;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReturnItem extends TenantModel
{
    use SoftDeletes;

    protected $table = 'purchase_return_items';

    protected $fillable = [
        'tenant_id',
        'purchase_return_id',
        'purchase_item_id',
        'product_id',
        'product_variant_id',
        'warehouse_id',
        'unit_id',
        'returned_quantity',
        'base_quantity',
        'unit_cost',
        'subtotal',
        'discount_amount',
        'tax_id',
        'tax_rate',
        'tax_amount',
        'line_total',
        'condition',
        'reason',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'returned_quantity' => 'decimal:4',
            'base_quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'line_total' => 'decimal:4',
            'condition' => PurchaseReturnCondition::class,
        ]);
    }

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class, 'purchase_return_id');
    }

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class, 'purchase_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(PurchaseReturnItemBatch::class, 'purchase_return_item_id');
    }

    public function serials(): HasMany
    {
        return $this->hasMany(PurchaseReturnItemSerial::class, 'purchase_return_item_id');
    }
}
