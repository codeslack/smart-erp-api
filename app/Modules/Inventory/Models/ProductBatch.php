<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Core\Models\TenantModel;

use App\Modules\Inventory\Enums\ProductBatchStatusEnum;

use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\Warehouse\Models\Warehouse;

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
                'expiry_date' => 'date',
                'received_at' => 'datetime',

                'unit_cost' => 'decimal:4',

                'original_quantity' => 'decimal:4',
                'remaining_quantity' => 'decimal:4',

                'status' => ProductBatchStatusEnum::class,
            ]
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class,
            'product_id'
        );
    }

    public function variant(): BelongsTo
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

    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function serials(): HasMany
    {
        return $this->hasMany(
            ProductSerial::class,
            'product_batch_id'
        );
    }

    public function stockLedgers(): HasMany
    {
        return $this->hasMany(
            StockLedger::class,
            'product_batch_id'
        );
    }
}