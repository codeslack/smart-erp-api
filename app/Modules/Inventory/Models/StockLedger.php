<?php

namespace App\Modules\Inventory\Models;

use App\Core\Tenant\Models\TenantModel;

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

        'balance_after',

        'remarks',
    ];
}