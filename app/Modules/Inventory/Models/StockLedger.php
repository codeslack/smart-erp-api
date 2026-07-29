<?php

namespace App\Modules\Inventory\Models;

use App\Core\Models\TenantModel;
use App\Modules\Product\Models\Product;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Product\Models\ProductBatch;
use App\Modules\Product\Models\ProductSerial;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;

class StockLedger extends TenantModel
{
    protected $table = 'stock_ledgers';

    protected function casts(): array
    {
        return [
            'transaction_date' => 'datetime',

            'qty_in' => 'decimal:4',
            'qty_out' => 'decimal:4',

            'unit_cost' => 'decimal:4',
            'line_cost' => 'decimal:4',

            'balance_quantity' => 'decimal:4',
            'balance_cost' => 'decimal:4',
            
            'transaction_type'
                => InventoryTransactionTypeEnum::class,
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

    public function batch(): BelongsTo
    {
        return $this->belongsTo(
            ProductBatch::class,
            'product_batch_id'
        );
    }

    public function serial(): BelongsTo
    {
        return $this->belongsTo(
            ProductSerial::class,
            'product_serial_id'
        );
    }
}
