<?php

namespace App\Modules\PurchaseReturn\Models;

use App\Core\Models\TenantModel;
use App\Modules\Inventory\Models\ProductBatch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReturnItemBatch extends TenantModel
{
    use SoftDeletes;

    protected $table = 'purchase_return_item_batches';

    protected $fillable = [
        'tenant_id',
        'purchase_return_id',
        'purchase_return_item_id',
        'product_batch_id',
        'batch_no',
        'quantity',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'quantity' => 'decimal:4',
        ]);
    }

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class, 'purchase_return_id');
    }

    public function purchaseReturnItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturnItem::class, 'purchase_return_item_id');
    }

    public function productBatch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }
}
