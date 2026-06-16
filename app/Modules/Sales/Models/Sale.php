<?php

namespace App\Modules\Sales\Models;

use App\Core\Tenant\Models\TenantModel;
use App\Modules\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends TenantModel
{
    protected $fillable = [
        'tenant_id',

        'sale_no',

        'customer_id',

        'sale_date',

        'subtotal',
        'discount',
        'tax',
        'grand_total',

        'status',

        'notes',
    ];

    protected $casts = [
        'sale_date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(
            Customer::class
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            SaleItem::class
        );
    }
}