<?php

namespace App\Modules\Customer\Models;

use App\Core\Tenant\Models\TenantModel;

class Customer extends TenantModel
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
