<?php

namespace App\Modules\GoodsReceiptNote\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Core\Models\TenantModel;

use App\Modules\Unit\Models\Unit;
use App\Modules\Tax\Models\TaxGroup;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\PurchaseOrder\Models\PurchaseOrderItem;

class GoodsReceiptNoteItem extends TenantModel
{
    protected $table = 'goods_receipt_note_items';

    protected $fillable = [
        'goods_receipt_note_id',
        'purchase_order_item_id',
        'product_id',
        'product_variant_id',
        'warehouse_id',
        'unit_id',

        'quantity_received',
        'base_quantity',
        'unit_cost',
        'base_unit_cost',
        'line_total',

        'tax_group_id',
        'tax_rate',
        'tax_amount',

        'remarks',

        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return array_merge(
            parent::casts(),
            [
                'quantity_received' => 'decimal:4',
                'base_quantity' => 'decimal:4',
                'unit_cost' => 'decimal:4',
                'base_unit_cost' => 'decimal:8',
                'line_total' => 'decimal:4',
                'tax_rate' => 'decimal:4',
                'tax_amount' => 'decimal:4',
            ]
        );
    }

    public function goodsReceiptNote(): BelongsTo
    {
        return $this->belongsTo(
            GoodsReceiptNote::class,
            'goods_receipt_note_id'
        );
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(
            PurchaseOrderItem::class,
            'purchase_order_item_id'
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

    public function unit(): BelongsTo
    {
        return $this->belongsTo(
            Unit::class,
            'unit_id'
        );
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(
            Warehouse::class,
            'warehouse_id'
        );
    }

    public function taxGroup(): BelongsTo
    {
        return $this->belongsTo(
            TaxGroup::class,
            'tax_group_id'
        );
    }

    public function batches(): HasMany
    {
        return $this->hasMany(
            GoodsReceiptNoteItemBatch::class,
            'goods_receipt_note_item_id'
        );
    }

    public function serials(): HasMany
    {
        return $this->hasMany(
            GoodsReceiptNoteItemSerial::class,
            'goods_receipt_note_item_id'
        );
    }
}