<?php

namespace App\Modules\SalesOrder\Models;

use App\Core\Models\TenantModel;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\Tax\Models\TaxGroup;
use App\Modules\Unit\Models\Unit;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrderItem extends TenantModel
{
    use SoftDeletes;

    protected $table = 'sales_order_items';

    protected $fillable = [
        'sales_order_id',
        'product_id',
        'product_variant_id',
        'warehouse_id',
        'line_type',
        'item_name',
        'sku',
        'description',
        'unit_of_measure_id',
        'quantity',
        'delivered_quantity',
        'invoiced_quantity',
        'cancelled_quantity',
        'unit_price',
        'discount_type',
        'discount_rate',
        'discount_amount',
        'net_unit_price',
        'subtotal_amount',
        'tax_group_id',
        'tax_rate',
        'is_tax_inclusive',
        'tax_base_amount',
        'tax_total_amount',
        'total_amount',
    ];

    protected function casts(): array
    {
        return array_merge(
            parent::casts(),
            [
                'quantity' => 'decimal:4',
                'delivered_quantity' => 'decimal:4',
                'invoiced_quantity' => 'decimal:4',
                'cancelled_quantity' => 'decimal:4',
                'unit_price' => 'decimal:4',
                'discount_rate' => 'decimal:4',
                'discount_amount' => 'decimal:4',
                'net_unit_price' => 'decimal:4',
                'subtotal_amount' => 'decimal:4',
                'tax_rate' => 'decimal:4',
                'tax_base_amount' => 'decimal:4',
                'tax_total_amount' => 'decimal:4',
                'total_amount' => 'decimal:4',
                'is_tax_inclusive' => 'boolean',
            ]
        );
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_of_measure_id');
    }

    public function taxGroup(): BelongsTo
    {
        return $this->belongsTo(TaxGroup::class, 'tax_group_id');
    }

    public function getUnitIdAttribute(): ?int
    {
        return $this->unit_of_measure_id;
    }

    public function setUnitIdAttribute(?int $value): void
    {
        $this->attributes['unit_of_measure_id'] = $value;
    }

    public function getPendingQuantityAttribute(): float
    {
        return max(
            0,
            (float) $this->quantity - (float) $this->delivered_quantity - (float) $this->cancelled_quantity
        );
    }

    public function getLineTotalAttribute(): float
    {
        return (float) $this->total_amount;
    }
}
