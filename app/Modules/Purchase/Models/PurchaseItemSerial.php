<?php

namespace App\Modules\Purchase\Models;

use App\Core\Models\TenantModel;
use App\Core\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseItemSerial extends TenantModel
{
    use HasUuidPrimaryKey;
    use SoftDeletes;

    protected $table = 'purchase_item_serials';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'purchase_id',
        'purchase_item_id',
        'serial_number',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class, 'purchase_item_id');
    }
}

