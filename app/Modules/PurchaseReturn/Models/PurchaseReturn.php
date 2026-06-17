<?php

namespace App\Modules\PurchaseReturn\Models;

use App\Core\Tenant\Models\TenantModel;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturn extends TenantModel
{
    protected $fillable = [
        'tenant_id',
        'purchase_id',
        'supplier_id',

        'return_no',
        'return_date',

        'grand_total',

        'status',
        'notes',
    ];

    protected $casts = [
        'return_date' => 'date',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(
            Purchase::class
        );
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(
            Supplier::class
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            PurchaseReturnItem::class
        );
    }
}