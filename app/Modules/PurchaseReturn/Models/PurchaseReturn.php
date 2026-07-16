<?php

namespace App\Modules\PurchaseReturn\Models;

use App\Modules\User\Models\User;
use App\Core\Tenant\Models\TenantModel;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Purchase\Models\Purchase;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnStatus;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnRefundType;

class PurchaseReturn extends TenantModel
{
    protected $fillable = [
        'tenant_id',

        'return_no',

        'purchase_id',
        'supplier_id',

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

        'refund_type'     => PurchaseReturnRefundType::class,

        'status'          => PurchaseReturnStatus::class,
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(
            Purchase::class
        )->withDefault();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(
            Supplier::class
        )->withDefault();
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            PurchaseReturnItem::class
        );
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'approved_by'
        );
    }
}