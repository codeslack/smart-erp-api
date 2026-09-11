<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Core\Models\TenantModel;

use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\Warehouse\Models\Warehouse;

use App\Modules\Inventory\Enums\InventoryCostLayerStatusEnum;

class InventoryCostLayer extends TenantModel
{
    protected $table = 'inventory_cost_layers';

    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */

    protected function casts(): array
    {
        return array_merge(
            parent::casts(),
            [

                'original_quantity' =>
                    'decimal:4',

                'remaining_quantity' =>
                    'decimal:4',

                'unit_cost' =>
                    'decimal:4',

                'layer_date' =>
                    'datetime',

                'status' =>
                    InventoryCostLayerStatusEnum::class,
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
    | Product Variant
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
    | Source Stock Ledger
    |--------------------------------------------------------------------------
    */

    public function stockLedger(): BelongsTo
    {
        return $this->belongsTo(
            StockLedger::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Business Helpers
    |--------------------------------------------------------------------------
    */

    public function isOpen(): bool
    {
        return bccomp(
            (string) $this->remaining_quantity,
            '0',
            4
        ) > 0;
    }

    public function isExhausted(): bool
    {
        return ! $this->isOpen();
    }

    public function getRemainingValueAttribute(): string
    {
        return bcmul(
            (string) $this->remaining_quantity,
            (string) $this->unit_cost,
            4
        );
    }
}