<?php

namespace App\Modules\SalesOrder\Models;

use App\Core\Tenant\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrder extends TenantModel
{
    protected $fillable = [
        'customer_id',
        'so_no',
        'order_date',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'grand_total',
        'status',
        'notes',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(
            \App\Modules\Customer\Models\Customer::class
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            SalesOrderItem::class
        );
    }
}
