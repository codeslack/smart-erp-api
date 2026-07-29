<?php

namespace App\Modules\Product\Models;

use App\Core\Models\TenantModel;
use App\Modules\Product\Enums\SerialStatusEnum;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductSerial extends TenantModel
{
    use SoftDeletes;
    
    protected $table = 'product_serials';

    protected function casts(): array
    {
        return [
            'purchase_cost' => 'decimal:4',
            
            'warranty_expiry' => 'date',

            'status' => SerialStatusEnum::class,
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