<?php

namespace App\Modules\Inventory\Models;

use App\Core\Tenant\Models\TenantModel;
use App\Modules\Product\Models\Product;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLedger extends TenantModel
{
    protected $fillable = [
        'tenant_id',
        'product_id',
        'warehouse_id',

        'transaction_type',

        'reference_type',
        'reference_id',

        'qty_in',
        'qty_out',

        'unit_cost',
        'line_cost',

        'balance_after',

        'remarks',
    ];

    protected $casts = [

        'qty_in'
            => 'decimal:4',

        'qty_out'
            => 'decimal:4',

        'unit_cost'
            => 'decimal:4',

        'line_cost'
            => 'decimal:4',

        'balance_after'
            => 'decimal:4',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class
        );
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(
            Warehouse::class
        );
    }
}
