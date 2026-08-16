<?php

namespace App\Modules\Product\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Core\Models\TenantModel;

use App\Modules\Inventory\Models\ProductBatch;
use App\Modules\Inventory\Models\ProductSerial;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\StockLedger;

class ProductVariant extends TenantModel
{
    use SoftDeletes;

    protected $table = 'product_variants';

    protected $fillable = [
        'product_id',
        'sku',
        'code',
        'barcode',
        'name',
        'purchase_price',
        'selling_price',
        'is_active',
    ];

    /**
     * Casts
     */
    protected function casts(): array
    {
        return array_merge(
            parent::casts(),
            [

                'purchase_price' => 'decimal:4',

                'selling_price' => 'decimal:4',

                'is_active' => 'boolean',
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
    | Variant Attributes
    |--------------------------------------------------------------------------
    */

    public function attributes(): HasMany
    {
        return $this->hasMany(
            ProductVariantAttribute::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Inventory
    |--------------------------------------------------------------------------
    */

    public function batches(): HasMany
    {
        return $this->hasMany(
            ProductBatch::class
        );
    }

    public function serials(): HasMany
    {
        return $this->hasMany(
            ProductSerial::class
        );
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(
            ProductStock::class
        );
    }

    public function stockLedgers(): HasMany
    {
        return $this->hasMany(
            StockLedger::class
        );
    }

    public function scopeActive(
        Builder $query
    ): Builder {
        return $query->where(
            'is_active',
            true
        );
    }

    public function getDisplayNameAttribute(): string
    {
        return trim(
            $this->product->display_name . ' ' .
            $this->name
        );
    }    
}
