<?php

namespace App\Modules\Sales\Models;

use App\Core\Models\TenantModel;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\SalesOrder\Models\SalesOrderItem;
use App\Modules\Tax\Models\Tax;
use App\Modules\Tax\Models\TaxGroup;
use App\Modules\Tax\Models\TaxApplicabilityRule;
use App\Modules\Unit\Models\Unit;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleItem extends TenantModel
{
    use SoftDeletes;

    protected $table = 'sale_items';

    protected $fillable = [
        'sale_id',
        'sales_order_item_id',
        'delivery_note_item_id',
        'product_id',
        'product_variant_id',
        'warehouse_id',
        'line_type',
        'item_name',
        'sku',
        'description',
        'unit_id',
        'quantity',
        'base_quantity',
        'unit_price',
        'cost_price',
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
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'quantity' => 'decimal:4',
            'base_quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'cost_price' => 'decimal:4',
            'discount_rate' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'net_unit_price' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'tax_snapshot' => 'array',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'line_total' => 'decimal:4',
            'total_amount' => 'decimal:4',
        ]);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function salesOrderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class, 'sales_order_item_id');
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

    public function taxGroup(): BelongsTo
    {
        return $this->belongsTo(TaxGroup::class, 'tax_group_id');
    }

    public function taxApplicabilityRule(): BelongsTo
    {
        return $this->belongsTo(TaxApplicabilityRule::class, 'tax_applicability_rule_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(SaleItemBatch::class, 'sale_item_id');
    }

    public function serials(): HasMany
    {
        return $this->hasMany(SaleItemSerial::class, 'sale_item_id');
    }
}
