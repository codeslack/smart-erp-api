<?php

namespace App\Modules\SalesReturn\Models;

use App\Core\Tenant\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesReturn extends TenantModel
{
    protected $fillable = [
        'tenant_id',
        'sale_id',
        'customer_id',
        'return_no',
        'return_date',
        'grand_total',
        'status',
        'notes',
    ];

    protected $casts = [
        'return_date' => 'date',
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
}
