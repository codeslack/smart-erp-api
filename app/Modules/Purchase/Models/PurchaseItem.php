<?php

namespace App\Modules\Purchase\Models;

use App\Core\Models\TenantModel;
use App\Core\Traits\HasUuidPrimaryKey;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Unit\Models\Unit;
use App\Modules\PurchaseOrder\Models\PurchaseOrderItem;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItem;
use App\Modules\Tax\Models\Tax;
use App\Modules\PurchaseReturn\Models\PurchaseReturnItem;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseItem extends TenantModel
{
    use HasUuidPrimaryKey;
    use SoftDeletes;

    protected $table = 'purchase_items';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'purchase_id',
        'purchase_order_item_id',
        'goods_receipt_note_item_id',
        'product_id',
        'product_variant_id',
        'warehouse_id',
        'unit_id',
        'quantity',
        'base_quantity',
        'unit_cost',
        'line_total',
        'subtotal',
        'discount_type',
        'discount_rate',
        'discount_amount',
        'tax_id',
        'tax_rate',
        'tax_amount',
        'total_amount',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'quantity' => 'decimal:4',
            'base_quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'line_total' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'discount_rate' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'total_amount' => 'decimal:4',
        ]);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    public function goodsReceiptNoteItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNoteItem::class, 'goods_receipt_note_item_id');
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(PurchaseItemBatch::class, 'purchase_item_id');
    }

    public function serials(): HasMany
    {
        return $this->hasMany(PurchaseItemSerial::class, 'purchase_item_id');
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class, 'purchase_item_id');
    }

    public function getReturnedQuantityAttribute(): float
    {
        return (float) $this->returnItems()
            ->whereHas('purchaseReturn', fn ($q) => $q->where('status', PurchaseReturnStatus::CONFIRMED))
            ->sum('returned_quantity');
    }

    public function getAvailableReturnQuantityAttribute(): float
    {
        return max(0, (float) $this->quantity - (float) $this->returned_quantity);
    }

    public function getDiscountPercentageAttribute(): float
    {
        return (float) ($this->discount_rate ?? 0);
    }

    public function setDiscountPercentageAttribute($value): void
    {
        $this->attributes['discount_rate'] = $value;
        if (($this->attributes['discount_type'] ?? 'none') === 'none' && (float) $value > 0) {
            $this->attributes['discount_type'] = 'percentage';
        }
    }
}
