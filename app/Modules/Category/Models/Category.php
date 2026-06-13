<?php

namespace App\Modules\Category\Models;

use App\Core\Tenant\Models\TenantModel;

class Category extends TenantModel
{
    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
