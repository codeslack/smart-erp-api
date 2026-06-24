<?php

namespace App\Modules\SupplierPayment\Models;

use App\Core\Tenant\Models\TenantModel;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPayment extends TenantModel
{
    protected $fillable = [

        'tenant_id',

        'payment_no',

        'supplier_id',

        'payment_date',

        'payment_method',

        'reference_no',

        'amount',

        'notes',

        'status',
    ];

    protected $casts = [

        'payment_date' => 'date',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(
            Supplier::class
        );
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(
            SupplierPaymentAllocation::class
        );
    }
}