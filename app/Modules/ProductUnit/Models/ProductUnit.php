<?php

namespace App\Modules\ProductUnit\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Core\Models\TenantModel;

use App\Modules\Product\Models\Product;
use App\Modules\Unit\Models\Unit;

class ProductUnit extends TenantModel
{
    use SoftDeletes;

    protected $table = 'product_units';

    protected $fillable = [
        'product_id',
        'unit_id',
        'conversion_factor',
        'is_purchase_unit',
        'is_sales_unit',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return array_merge(
            parent::casts(),
            [
                'conversion_factor' => 'decimal:8',

                'is_purchase_unit' => 'boolean',
                'is_sales_unit' => 'boolean',
                'is_active' => 'boolean',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class,
            'product_id'
        );
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(
            Unit::class,
            'unit_id'
        );
    }
}