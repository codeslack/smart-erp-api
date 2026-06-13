<?php

namespace App\Modules\Warehouse\Models;

use App\Core\Tenant\Models\TenantModel;

class Warehouse extends TenantModel
{
    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'phone',
        'email',
        'address',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}