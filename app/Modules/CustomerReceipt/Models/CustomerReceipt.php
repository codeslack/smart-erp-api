<?php

namespace App\Modules\CustomerReceipt\Models;

use App\Core\Tenant\Models\TenantModel;
use App\Modules\Customer\Models\Customer;
use App\Modules\Accounting\Models\ChartOfAccount;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\CustomerReceipt\Enums\CustomerReceiptType;
use App\Modules\AdvanceAllocation\Models\AdvanceAllocation;
class CustomerReceipt extends TenantModel
{
    protected $fillable = [

        'tenant_id',

        'receipt_no',

        'customer_id',

        'receipt_date',

        'receipt_type',

        'payment_method',

        'reference_no',

        'amount',

        'payment_account_id',

        'notes',

        'status',
    ];

    protected $casts = [

        'receipt_date' => 'date',

        'receipt_type' => CustomerReceiptType::class,
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

    public function getAvailableAdvanceAttribute(): float
    {
        return max(
            0,
            $this->amount -
            $this->allocated_amount
        );
    }
}