<?php

namespace App\Modules\SalesReturn\Models;

use App\Core\Models\TenantModel;
use App\Modules\Inventory\Models\ProductSerial;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleReturnItemSerial extends TenantModel
{
    use SoftDeletes;

    protected $table = 'sale_return_item_serials';

    protected $fillable = [
        'sales_return_id',
        'sales_return_item_id',
        'product_serial_id',
        'serial_number',
    ];

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class, 'sales_return_id');
    }

    public function salesReturnItem(): BelongsTo
    {
        return $this->belongsTo(SalesReturnItem::class, 'sales_return_item_id');
    }

    public function serial(): BelongsTo
    {
        return $this->belongsTo(ProductSerial::class, 'product_serial_id');
    }
}
