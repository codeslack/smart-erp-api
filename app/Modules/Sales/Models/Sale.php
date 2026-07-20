<?php

namespace App\Modules\Sales\Models;

use App\Core\Tenant\Models\TenantModel;
use App\Modules\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\AdvanceAllocation\Models\AdvanceAllocation;
use App\Modules\CustomerReceipt\Models\CustomerReceiptAllocation;

class Sale extends TenantModel
{
    protected $fillable = [
        'tenant_id',

        'sale_no',

        'customer_id',

        'sale_date',

        'subtotal',
        'discount',
        'tax',
        'grand_total',

        'paid_amount',
        'due_amount',

        'status',

        'notes',
    ];

    protected $casts = [
        'sale_date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(
            Customer::class
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            SaleItem::class
        );
    }

    public function receiptAllocations(): HasMany
    {
        return $this->hasMany(
            CustomerReceiptAllocation::class
        );
    }

    public function advanceAllocations(): HasMany
    {
        return $this->hasMany(
            AdvanceAllocation::class,
            'target_id'
        )
        ->where(
            'target_type',
            self::class
        );
    }    
}
