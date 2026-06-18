<?php

namespace App\Modules\StockAdjustment\Models;

use App\Core\Tenant\Models\TenantModel;

class StockAdjustment extends TenantModel
{
    protected $fillable = [
        'adjustment_no',
        'adjustment_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'adjustment_date' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(
            StockAdjustmentItem::class
        );
    }
}
