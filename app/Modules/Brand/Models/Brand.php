<?php

namespace App\Modules\Brand\Models;

use App\Core\Tenant\Models\TenantModel;

class Brand extends TenantModel
{
    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
