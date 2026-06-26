<?php

namespace App\Modules\Customer\Models;

use App\Modules\Sales\Models\Sale;
use App\Core\Tenant\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Modules\CustomerReceipt\Models\CustomerReceipt;

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

    public function sales(): HasMany
    {
        return $this->hasMany(
            Sale::class
        );
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(
            CustomerReceipt::class
        );
    }
}
