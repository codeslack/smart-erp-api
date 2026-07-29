<?php

namespace App\Modules\Product\Models;

use App\Core\Models\TenantModel;
use App\Modules\Brand\Models\Brand;
use App\Modules\Category\Models\Category;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\StockLedger;
use App\Modules\Unit\Models\Unit;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Product\Enums\ProductTypeEnum;
use App\Modules\Product\Enums\ProductStatusEnum;
use App\Modules\Product\Enums\InventoryTrackingTypeEnum;

/**
 * @property string $uuid
 * @property string $sku
 * @property string $name
 */
class Product extends TenantModel
{
    use SoftDeletes;

    protected $table = 'products';

    protected function casts(): array
    {
        return [
            'product_type' => ProductTypeEnum::class,

            'status' => ProductStatusEnum::class,

            'inventory_tracking_type'
                => InventoryTrackingTypeEnum::class,

            'track_inventory' => 'boolean',
            'track_batch' => 'boolean',
            'track_serial' => 'boolean',

            'has_expiry' => 'boolean',
            'has_warranty' => 'boolean',
            'requires_prescription' => 'boolean',

            'purchase_price' => 'decimal:4',
            'selling_price' => 'decimal:4',

            'minimum_stock' => 'decimal:4',
            'maximum_stock' => 'decimal:4',
            'reorder_level' => 'decimal:4',
            'critical_level' => 'decimal:4',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            Category::class
        );
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(
            Brand::class
        );
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(
            Unit::class
        );
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function serials(): HasMany
    {
        return $this->hasMany(ProductSerial::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function stockLedgers(): HasMany
    {
        return $this->hasMany(StockLedger::class);
    }
}
