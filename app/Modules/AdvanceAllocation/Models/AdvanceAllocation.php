<?php

namespace App\Modules\AdvanceAllocation\Models;

use App\Core\Tenant\Models\TenantModel;

class AdvanceAllocation extends TenantModel
{
    protected $fillable = [

        'tenant_id',

        'allocation_type',

        'source_type',
        'source_id',

        'target_type',
        'target_id',

        'allocated_amount',

        'allocated_at',

        'created_by',
    ];

    protected $casts = [

        'allocated_amount' => 'decimal:4',

        'allocated_at' => 'datetime',
    ];

    public function source()
    {
        return $this->morphTo();
    }

    public function target()
    {
        return $this->morphTo();
    }    
}