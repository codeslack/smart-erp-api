<?php

namespace App\Modules\CustomerReceipt\Models;

use App\Core\Tenant\Models\TenantModel;
use App\Modules\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerReceipt extends TenantModel
{
    protected $fillable = [

        'tenant_id',

        'receipt_no',

        'customer_id',

        'receipt_date',

        'payment_method',

        'reference_no',

        'amount',

        'notes',

        'status',
    ];

    protected $casts = [

        'receipt_date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(
            Customer::class
        );
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(
            CustomerReceiptAllocation::class
        );
    }
}