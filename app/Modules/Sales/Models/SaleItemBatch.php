<?php

namespace App\Modules\Sales\Models;

use App\Core\Models\TenantModel;
use App\Modules\Inventory\Models\ProductBatch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleItemBatch extends TenantModel
{
    use SoftDeletes;

    protected $table = 'sale_item_batches';

    protected $fillable = [
        'sale_id',
        'sale_item_id',
        'product_batch_id',
        'batch_no',
        'quantity',
        'base_quantity',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'quantity' => 'decimal:4',
            'base_quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
        ]);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class, 'sale_item_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }
}
