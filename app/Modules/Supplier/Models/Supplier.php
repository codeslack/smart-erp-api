<?php

namespace App\Modules\Supplier\Models;

use App\Core\Tenant\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Modules\SupplierPayment\Models\SupplierPayment;

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

    public function payments(): HasMany
    {
        return $this->hasMany(
            SupplierPayment::class
        );
    }
}
