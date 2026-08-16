<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Core\Models\TenantModel;

use App\Modules\User\Models\User;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;

use App\Modules\Warehouse\Models\Warehouse;

use App\Modules\Inventory\Enums\StockTransactionTypeEnum;

class StockLedger extends TenantModel
{
    /**
     * Casts
     */
    protected function casts(): array
    {
        return array_merge(
            parent::casts(),
            [

                'transaction_date' => 'datetime',

                'quantity_in' => 'decimal:4',

                'quantity_out' => 'decimal:4',

                'unit_cost' => 'decimal:4',

                'total_cost' => 'decimal:4',

                'balance_quantity' => 'decimal:4',

                'balance_average_cost' => 'decimal:4',

                'transaction_type' =>
                    StockTransactionTypeEnum::class,
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
    | Serial
    |--------------------------------------------------------------------------
    */

    public function serial(): BelongsTo
    {
        return $this->belongsTo(
            ProductSerial::class,
            'product_serial_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Reference Document
    |--------------------------------------------------------------------------
    */

    public function referenceable(): MorphTo
    {
        return $this->morphTo();
    }

    /*
    |--------------------------------------------------------------------------
    | Creator
    |--------------------------------------------------------------------------
    */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }
}
