<?php

namespace App\Modules\Customer\Models;

use App\Core\Tenant\TenantModel;

class Customer extends TenantModel
{
    protected $fillable = [
        'tenant_id',
        'name',
    ];
}
