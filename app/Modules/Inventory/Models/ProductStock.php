<?php

namespace App\Modules\Inventory\Models;

use App\Core\Models\TenantModel;
use App\Modules\Product\Models\Product;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStock extends TenantModel
{
    protected $table = 'product_stocks';

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',

            'average_cost' => 'decimal:4',

            'inventory_value' => 'decimal:4',
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
