<?php

namespace App\Modules\Receipt\Models;

use App\Modules\Sales\Models\Sale;
use App\Core\Tenant\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptAllocation extends TenantModel
{
    protected $fillable = [

        'tenant_id',

        'receipt_id',

        'sale_id',

        'allocated_amount',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(
            Receipt::class,
            'receipt_id'
        );
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(
            Sale::class
        );
    }
}
