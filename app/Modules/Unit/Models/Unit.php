<?php

namespace App\Modules\Unit\Models;

use App\Core\Tenant\Models\TenantModel;

class Unit extends TenantModel
{
    protected $fillable = [
        'tenant_id',
        'name',
        'short_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}