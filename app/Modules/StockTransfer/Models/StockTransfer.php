<?php

namespace App\Modules\StockTransfer\Models;

use App\Core\Tenant\Models\TenantModel;

class StockTransfer extends TenantModel
{
    protected $fillable = [
        'transfer_no',
        'from_warehouse_id',
        'to_warehouse_id',
        'transfer_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'transfer_date' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(
            StockTransferItem::class
        );
    }

    public function fromWarehouse()
    {
        return $this->belongsTo(
            \App\Modules\Warehouse\Models\Warehouse::class,
            'from_warehouse_id'
        );
    }

    public function toWarehouse()
    {
        return $this->belongsTo(
            \App\Modules\Warehouse\Models\Warehouse::class,
            'to_warehouse_id'
        );
    }
}
