<?php

namespace App\Modules\Supplier\Models;

use App\Core\Tenant\Models\TenantModel;

class Supplier extends TenantModel
{
    protected $fillable = [
        'tenant_id',

        'name',
        'code',

        'contact_person',

        'phone',
        'email',

        'address',

        'tax_number',

        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}