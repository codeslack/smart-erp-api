<?php

namespace App\Modules\Payment\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Core\Models\TenantModel;

class PaymentAllocation extends TenantModel
{
    protected $table = 'payment_allocations';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'payment_id',
        'document_type',
        'document_id',
        'allocated_amount',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:4',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}