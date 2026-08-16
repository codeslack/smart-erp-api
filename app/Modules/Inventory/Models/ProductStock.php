<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Core\Models\TenantModel;

use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;

use App\Modules\Warehouse\Models\Warehouse;

class ProductStock extends TenantModel
{
    use SoftDeletes;

    protected $table = 'product_stocks';

    /**
     * Casts
     */
    protected function casts(): array
    {
        return array_merge(
            parent::casts(),
            [

                'quantity' => 'decimal:4',

                'average_cost' => 'decimal:4',

                'last_movement_at' => 'datetime',
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
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getInventoryValueAttribute(): string
    {
        return bcmul(
            (string) $this->quantity,
            (string) $this->average_cost,
            4
        );
    }
}
