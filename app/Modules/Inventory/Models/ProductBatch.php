<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Modules\Product\Enums\BatchStatusEnum;

use App\Core\Models\TenantModel;


use App\Modules\Product\Models\Product;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Product\Models\ProductVariant;

class ProductBatch extends TenantModel
{
    use SoftDeletes;
    
    protected $table = 'product_batches';

    protected function casts(): array
    {
        return array_merge(
            parent::casts(),
            [
                'manufacturing_date' => 'date',

                'received_at' => 'datetime',

                'expiry_date' => 'date',

                'unit_cost' => 'decimal:4',

                'original_quantity' => 'decimal:4',
                'remaining_quantity' => 'decimal:4',

                'status' => BatchStatusEnum::class,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Product
    |--------------------------------------------------------------------------
    */

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Variant
    |--------------------------------------------------------------------------
    */
    
    public function variant(): BelongsTo
    {
        return $this->belongsTo(
            ProductVariant::class,
            'product_variant_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Warehouse
    |--------------------------------------------------------------------------
    */

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(
            Warehouse::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Source Document
    |--------------------------------------------------------------------------
    |
    | Opening Stock
    | Purchase
    | Production
    | Adjustment
    |
    */

    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }

    /*
    |--------------------------------------------------------------------------
    | Serials
    |--------------------------------------------------------------------------
    */

    public function serials(): HasMany
    {
        return $this->hasMany(
            ProductSerial::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Stock Ledger
    |--------------------------------------------------------------------------
    */

    public function stockLedgers(): HasMany
    {
        return $this->hasMany(
            StockLedger::class
        );
    }
}