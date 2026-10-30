<?php

namespace App\Modules\SalesReturn\Models;

use App\Core\Models\TenantModel;
use App\Modules\Inventory\Models\ProductBatch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleReturnItemBatch extends TenantModel
{
    use SoftDeletes;

    protected $table = 'sale_return_item_batches';

    protected $fillable = [
        'tenant_id',
        'sales_return_id',
        'sales_return_item_id',
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

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class, 'sales_return_id');
    }

    public function salesReturnItem(): BelongsTo
    {
        return $this->belongsTo(SalesReturnItem::class, 'sales_return_item_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }
}
