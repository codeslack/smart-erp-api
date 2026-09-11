<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Core\Models\TenantModel;

class InventoryCostLayerConsumption extends TenantModel
{
    protected $table = 'inventory_cost_layer_consumptions';

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

                'quantity' =>
                    'decimal:4',

                'unit_cost' =>
                    'decimal:4',

                'total_cost' =>
                    'decimal:4',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FIFO Cost Layer
    |--------------------------------------------------------------------------
    */

    public function inventoryCostLayer(): BelongsTo
    {
        return $this->belongsTo(
            InventoryCostLayer::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Stock Ledger
    |--------------------------------------------------------------------------
    */

    public function stockLedger(): BelongsTo
    {
        return $this->belongsTo(
            StockLedger::class
        );
    }
}