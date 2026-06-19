<?php

namespace App\Modules\SalesQuotation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesQuotationItem extends Model
{
    protected $fillable = [
        'sales_quotation_id',
        'product_id',
        'warehouse_id',
        'quantity',
        'unit_price',
        'line_total',
    ];

    public function salesQuotation(): BelongsTo
    {
        return $this->belongsTo(
            SalesQuotation::class
        );
    }
}
