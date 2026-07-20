<?php

namespace App\Modules\SupplierPayment\Models;

use App\Core\Tenant\Models\TenantModel;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Accounting\Models\ChartOfAccount;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\SupplierPayment\Enums\SupplierPaymentType;
use App\Modules\AdvanceAllocation\Models\AdvanceAllocation;

class SupplierPayment extends TenantModel
{
    protected $fillable = [

        'tenant_id',

        'payment_no',

        'supplier_id',

        'payment_date',

        'payment_type',

        'payment_method',

        'reference_no',

        'amount',

        'payment_account_id',

        'notes',

        'status',
    ];

    protected $casts = [

        'payment_date' => 'date',

        'payment_type' => SupplierPaymentType::class,
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

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(
            ChartOfAccount::class,
            'payment_account_id'
        );
    }

    public function advanceAllocations(): HasMany
    {
        return $this->hasMany(
            AdvanceAllocation::class,
            'source_id'
        )
        ->where(
            'source_type',
            self::class
        );
    }

    public function getAllocatedAmountAttribute(): float
    {
        if ($this->relationLoaded('advanceAllocations')) {

            return (float)
                $this->advanceAllocations
                    ->sum('allocated_amount');
        }

        return (float)
            $this->advanceAllocations()
                ->sum('allocated_amount');
    }

    public function getUnallocatedAmountAttribute(): float
    {
        return max(
            0,
            $this->amount -
            $this->allocated_amount
        );
    }    

    public function getAvailableAdvanceAttribute(): float
    {
        return $this->unallocated_amount;
    }
}