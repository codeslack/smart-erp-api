<?php

namespace App\Modules\Purchase\Models;

use App\Core\Tenant\Models\TenantModel;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Purchase\Models\PurchaseItem;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\AdvanceAllocation\Models\AdvanceAllocation;
use App\Modules\SupplierPayment\Models\SupplierPaymentAllocation;

class Purchase extends TenantModel
{
    protected $fillable = [
        'tenant_id',
        'supplier_id',

        'purchase_no',
        'purchase_date',

        'subtotal',
        'discount_amount',
        'tax_amount',
        'grand_total',

        'paid_amount',
        'due_amount',

        'notes',
        'status',
    ];

    protected $casts = [
        'purchase_date' => 'date',

        'subtotal' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'grand_total' => 'decimal:4',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(
            Supplier::class
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            PurchaseItem::class
        );
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(
            SupplierPaymentAllocation::class
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
