<?php

namespace App\Modules\PurchaseOrder\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Core\Models\TenantModel;

use App\Modules\Unit\Models\Unit;
use App\Modules\Tax\Models\TaxGroup;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\Warehouse\Models\Warehouse;

class PurchaseOrderItem extends TenantModel
{
    use SoftDeletes;

    protected $table = 'purchase_order_items';

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'product_variant_id',
        'warehouse_id',
        'line_type',
        'item_name',
        'sku',
        'description',
        'unit_of_measure_id',
        'quantity',
        'received_quantity',
        'billed_quantity',
        'cancelled_quantity',
        'unit_price',
        'discount_type',
        'discount_rate',
        'discount_amount',
        'net_unit_price',
        'subtotal_amount',
        'tax_group_id',
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
               'received_quantity' => 'decimal:4',
               'billed_quantity' => 'decimal:4',
               'cancelled_quantity' => 'decimal:4',

               'unit_price' => 'decimal:4',
               'discount_rate' => 'decimal:4',
               'discount_amount' => 'decimal:4',
               'net_unit_price' => 'decimal:4',
               'subtotal_amount' => 'decimal:4',
               'tax_base_amount' => 'decimal:4',
               'tax_total_amount' => 'decimal:4',
               'total_amount' => 'decimal:4',

               'is_tax_inclusive' => 'boolean',
            ]
        );
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(
            PurchaseOrder::class,
            'purchase_order_id'
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class,
            'product_id'
        );
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(
            ProductVariant::class,
            'product_variant_id'
        );
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(
            Warehouse::class,
            'warehouse_id'
        );
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(
            Unit::class,
            'unit_of_measure_id'
        );
    }

    public function taxGroup(): BelongsTo
    {
        return $this->belongsTo(
            TaxGroup::class,
            'tax_group_id'
        );
    }
}