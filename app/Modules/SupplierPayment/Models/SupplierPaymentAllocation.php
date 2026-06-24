<?php

namespace App\Modules\SupplierPayment\Models;

use App\Core\Tenant\Models\TenantModel;
use App\Modules\Purchase\Models\Purchase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPaymentAllocation extends TenantModel
{
    protected $fillable = [

        'tenant_id',

        'supplier_payment_id',

        'purchase_id',

        'allocated_amount',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(
            SupplierPayment::class,
            'supplier_payment_id'
        );
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(
            Purchase::class
        );
    }
}