<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Core\Models\TenantModel;

use App\Modules\Inventory\Enums\ProductSerialStatusEnum;

use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\Warehouse\Models\Warehouse;

class ProductSerial extends TenantModel
{
    use SoftDeletes;

    protected $table = 'product_serials';

    protected function casts(): array
    {
        return array_merge(
            parent::casts(),
            [
                'purchase_cost' => 'decimal:4',

                'warranty_expiry' => 'date',

                'sold_at' => 'datetime',

                'status' => ProductSerialStatusEnum::class,
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
        return $this->belongsTo(
            Product::class
        );
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
    | Batch
    |--------------------------------------------------------------------------
    */

    public function batch(): BelongsTo
    {
        return $this->belongsTo(
            ProductBatch::class,
            'product_batch_id'
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
