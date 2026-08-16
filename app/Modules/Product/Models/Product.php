<?php

namespace App\Modules\Product\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Core\Models\TenantModel;

use App\Modules\Unit\Models\Unit;
use App\Modules\Brand\Models\Brand;
use App\Modules\Category\Models\Category;

use App\Modules\Inventory\Models\ProductBatch;
use App\Modules\Inventory\Models\ProductSerial;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\StockLedger;

use App\Modules\Product\Enums\ProductTypeEnum;
use App\Modules\Product\Enums\InventoryTrackingTypeEnum;

class Product extends TenantModel
{
    use SoftDeletes;

    protected $table = 'products';

    protected $fillable = [
        'category_id',
        'brand_id',
        'unit_id',

        'name',
        'description',
        'product_type',
        'inventory_tracking_type',

        'has_expiry',
        'has_variants',
        'has_warranty',
        'is_active',

        'purchase_price',
        'selling_price',

        'minimum_stock',
        'maximum_stock',
        'reorder_level',
        'critical_level',

        'manufacturer',
        'model_number',
        'part_number',

        'weight',
        'length',
        'width',
        'height',

        'sku',
        'slug',
        'code',
        'barcode',

    ];

    protected function casts(): array
    {
        return array_merge(
            parent::casts(),
            [
                'product_type' => ProductTypeEnum::class,
                'inventory_tracking_type' => InventoryTrackingTypeEnum::class,

                'has_expiry' => 'boolean',
                'has_variants' => 'boolean',
                'has_warranty' => 'boolean',
                'is_active' => 'boolean',

                'purchase_price' => 'decimal:4',
                'selling_price' => 'decimal:4',

                'minimum_stock' => 'decimal:4',
                'maximum_stock' => 'decimal:4',
                'reorder_level' => 'decimal:4',
                'critical_level' => 'decimal:4',

                'weight' => 'decimal:4',
                'length' => 'decimal:4',
                'width' => 'decimal:4',
                'height' => 'decimal:4',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Master Relationships
    |--------------------------------------------------------------------------
    */

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Variants
    |--------------------------------------------------------------------------
    */

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Inventory Relationships
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(
        Builder $query
    ): Builder {
        return $query->where(
            'is_active',
            true
        );
    }

    public function scopeServices(
        Builder $query
    ): Builder {
        return $query->where(
            'product_type',
            ProductTypeEnum::SERVICE
        );
    }

    public function scopeInventory(
        Builder $query
    ): Builder {
        return $query->whereIn(
            'product_type',
            [
                ProductTypeEnum::PRODUCT,
                ProductTypeEnum::RAW_MATERIAL,
                ProductTypeEnum::FINISHED_GOOD,
                ProductTypeEnum::CONSUMABLE,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Business Helpers
    |--------------------------------------------------------------------------
    */

    public function isService(): bool
    {
        return $this->product_type
            === ProductTypeEnum::SERVICE;
    }

    public function requiresStock(): bool
    {
        return ! $this->isService();
    }

    public function isBatchTracked(): bool
    {
        return $this->inventory_tracking_type
            === InventoryTrackingTypeEnum::BATCH;
    }

    public function isSerialTracked(): bool
    {
        return $this->inventory_tracking_type
            === InventoryTrackingTypeEnum::SERIAL;
    }

    public function hasInventoryTracking(): bool
    {
        return $this->inventory_tracking_type
            !== InventoryTrackingTypeEnum::NONE;
    }

    public function supportsVariants(): bool
    {
        return ! $this->isService()
            && $this->has_variants;
    }

    public function hasExpiryTracking(): bool
    {
        return $this->has_expiry;
    }

    public function hasWarrantyTracking(): bool
    {
        return $this->has_warranty;
    }

    public function getDisplayNameAttribute(): string
    {
        return trim(
            ($this->brand?->name ?? '') . ' ' .
            $this->name
        );
    }

    public function isLocked(): bool
    {
        return $this->stockLedgers()->exists();
    }

    protected static function booted(): void
    {
        parent::booted();

        static::deleting(function (Product $product) {

            if (! $product->isForceDeleting()) {
                $product->variants()->delete();
            }
        });

        static::restoring(function (Product $product) {

            $product->variants()
                ->withTrashed()
                ->restore();
        });
    }
}
