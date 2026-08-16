<?php

namespace App\Modules\OpeningStock\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Core\Models\BaseModel;

use App\Modules\Product\Models\Product;
use App\Modules\Inventory\Models\ProductBatch;
use App\Modules\Inventory\Models\ProductSerial;
use App\Modules\Product\Models\ProductVariant;

class OpeningStockItem extends BaseModel
{
    protected $table = 'opening_stock_items';

    protected $fillable = [
        'opening_stock_id',

        'product_id',
        'product_variant_id',

        'product_batch_id',
        'product_serial_id',

        'quantity',

        'unit_cost',
        'total_cost',

        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'quantity'   => 'decimal:4',
            'unit_cost'  => 'decimal:4',
            'total_cost' => 'decimal:4',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function openingStock(): BelongsTo
    {
        return $this->belongsTo(
            OpeningStock::class
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class
        );
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(
            ProductVariant::class,
            'product_variant_id'
        );
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(
            ProductBatch::class,
            'product_batch_id'
        );
    }

    public function serial(): BelongsTo
    {
        return $this->belongsTo(
            ProductSerial::class,
            'product_serial_id'
        );
    }
}