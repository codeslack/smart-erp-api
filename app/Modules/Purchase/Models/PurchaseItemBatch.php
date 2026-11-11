<?php

namespace App\Modules\Purchase\Models;

use App\Core\Models\TenantModel;
use App\Core\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseItemBatch extends TenantModel
{
    use HasUuidPrimaryKey;
    use SoftDeletes;

    protected $table = 'purchase_item_batches';

    protected $fillable = [
        'uuid',
        'purchase_id',
        'purchase_item_id',
        'batch_no',
        'quantity',
        'base_quantity',
        'manufacturing_date',
        'expiry_date',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'base_quantity' => 'decimal:4',
        'manufacturing_date' => 'date',
        'expiry_date' => 'date',
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

