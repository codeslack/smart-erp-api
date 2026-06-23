<?php

namespace App\Modules\CustomerReceipt\Models;

use App\Modules\Sales\Models\Sale;
use App\Core\Tenant\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerReceiptAllocation extends TenantModel
{
    protected $fillable = [

        'tenant_id',

        'customer_receipt_id',

        'sale_id',

        'allocated_amount',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(
            CustomerReceipt::class,
            'customer_receipt_id'
        );
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(
            Sale::class
        );
    }
}