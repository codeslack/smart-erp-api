<?php

namespace App\Modules\SalesReturn\Models;

use App\Core\Tenant\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\SalesReturn\Enums\SalesReturnStatus;
use App\Modules\SalesReturn\Enums\SalesReturnRefundType;

class SalesReturn extends TenantModel
{
    protected $fillable = [
        'tenant_id',

        'return_no',

        'sale_id',
        'customer_id',

        'return_date',

        'subtotal',
        'discount',
        'tax',
        'grand_total',

        'refund_amount',
        'credited_amount',

        'refund_type',
        'return_reason',

        'status',

        'approved_by',
        'approved_at',

        'notes',
    ];

    protected $casts = [
        'return_date'     => 'date',

        'subtotal'        => 'decimal:4',
        'discount'        => 'decimal:4',
        'tax'             => 'decimal:4',
        'grand_total'     => 'decimal:4',

        'refund_amount'   => 'decimal:4',
        'credited_amount' => 'decimal:4',

        'approved_at'     => 'datetime',

        'refund_type' => SalesReturnRefundType::class,

        'status' => SalesReturnStatus::class,
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(
            \App\Modules\Sales\Models\Sale::class
        );
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(
            \App\Modules\Customer\Models\Customer::class
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            SalesReturnItem::class
        );
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(
            \App\Modules\User\Models\User::class,
            'approved_by'
        );
    }
}
