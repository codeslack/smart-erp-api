<?php

namespace App\Modules\DeliveryNote\Models;

use App\Core\Tenant\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryNote extends TenantModel
{
    protected $fillable = [
        'sales_order_id',
        'customer_id',
        'dn_no',
        'delivery_date',
        'grand_total',
        'status',
        'notes',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(
            \App\Modules\SalesOrder\Models\SalesOrder::class
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
            DeliveryNoteItem::class
        );
    }
}
