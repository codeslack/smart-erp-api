<?php

namespace App\Modules\PurchaseReturn\Models;

use App\Core\Models\TenantModel;
use App\Modules\Inventory\Models\ProductSerial;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReturnItemSerial extends TenantModel
{
    use SoftDeletes;

    protected $table = 'purchase_return_item_serials';

    protected $fillable = [
        'tenant_id',
        'purchase_return_id',
        'purchase_return_item_id',
        'product_serial_id',
        'serial_number',
    ];

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class, 'purchase_return_id');
    }

    public function purchaseReturnItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturnItem::class, 'purchase_return_item_id');
    }

    public function productSerial(): BelongsTo
    {
        return $this->belongsTo(ProductSerial::class, 'product_serial_id');
    }
}
