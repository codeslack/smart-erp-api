<?php

namespace App\Modules\Inventory\Models;

use App\Core\Tenant\Models\TenantModel;

class ProductStock extends TenantModel
{
    protected $fillable = [
        'tenant_id',
        'product_id',
        'warehouse_id',
        'quantity',
    ];
}