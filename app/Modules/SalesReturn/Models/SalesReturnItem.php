<?php

namespace App\Modules\SalesReturn\Models;

use App\Core\Models\TenantModel;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\SalesReturn\Enums\SalesReturnCondition;
use App\Modules\Tax\Models\Tax;
use App\Modules\Unit\Models\Unit;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesReturnItem extends TenantModel
{
    use SoftDeletes;

    protected $table = 'sales_return_items';

    protected $fillable = [
        'uuid',
        'sales_return_id',
        'sale_item_id',
        'product_id',
        'product_variant_id',
        'warehouse_id',
        'unit_id',
        'line_type',
        'item_name',
        'sku',
        'description',
        'returned_quantity',
        'base_quantity',
        'unit_price',
        'unit_cost',
        'discount_type',
        'discount_rate',
        'discount_amount',
        'net_unit_price',
        'subtotal',
        'tax_id',
        'tax_group_id',
        'tax_applicability_rule_id',
        'tax_snapshot',
        'tax_rate',
        'tax_amount',
        'line_total',
        'total_amount',
        'condition',
        'reason',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'returned_quantity' => 'decimal:4',
            'base_quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'discount_rate' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'net_unit_price' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'tax_snapshot' => 'array',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'line_total' => 'decimal:4',
            'total_amount' => 'decimal:4',
            'condition' => SalesReturnCondition::class,
        ]);
    }

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class, 'sales_return_id');
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class, 'sale_item_id');
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

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(SaleReturnItemBatch::class, 'sales_return_item_id');
    }

    public function serials(): HasMany
    {
        return $this->hasMany(SaleReturnItemSerial::class, 'sales_return_item_id');
    }

    public function getQuantityAttribute(): float
    {
        return (float) $this->returned_quantity;
    }

    public function setQuantityAttribute($value): void
    {
        $this->attributes['returned_quantity'] = $value;
    }
}
