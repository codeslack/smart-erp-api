<?php

namespace App\Modules\SalesReturn\Models;

use App\Core\Tenant\Models\TenantModel;

class SalesReturn extends TenantModel
{
    protected $fillable = [
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

    public function sale()
    {
        return $this->belongsTo(
            \App\Modules\Sales\Models\Sale::class
        );
    }

    public function customer()
    {
        return $this->belongsTo(
            \App\Modules\Customer\Models\Customer::class
        );
    }

    public function items()
    {
        return $this->hasMany(
            SalesReturnItem::class
        );
    }
}
