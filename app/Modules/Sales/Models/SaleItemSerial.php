<?php

namespace App\Modules\Sales\Models;

use App\Core\Models\TenantModel;
use App\Modules\Inventory\Models\ProductSerial;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleItemSerial extends TenantModel
{
    use SoftDeletes;

    protected $table = 'sale_item_serials';

    protected $fillable = [
        'sale_id',
        'sale_item_id',
        'product_serial_id',
        'serial_number',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
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

    public function serial(): BelongsTo
    {
        return $this->belongsTo(ProductSerial::class, 'product_serial_id');
    }
}
