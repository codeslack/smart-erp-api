<?php

namespace App\Modules\Product\Models;

use App\Core\Models\TenantModel;
use App\Modules\Product\Enums\BatchStatusEnum;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductBatch extends TenantModel
{
    use SoftDeletes;
    
    protected $table = 'product_batches';

    protected function casts(): array
    {
        return [
            'manufacturing_date' => 'date',
            'expiry_date' => 'date',

            'unit_cost' => 'decimal:4',

            'original_quantity' => 'decimal:4',
            'remaining_quantity' => 'decimal:4',

            'status' => BatchStatusEnum::class,
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(
            Warehouse::class
        );
    }
}