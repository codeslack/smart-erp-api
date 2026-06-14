<?php

namespace App\Modules\Product\Models;

use App\Core\Tenant\TenantModel;
use App\Modules\Unit\Models\Unit;
use App\Modules\Brand\Models\Brand;
use App\Modules\Category\Models\Category;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Product extends TenantModel
{
    protected $fillable = [
        'tenant_id',

        'category_id',
        'unit_id',
        'brand_id',

        'sku',
        'barcode',

        'name',
        'description',

        'purchase_price',
        'sale_price',

        'minimum_stock',
        
        'is_active',
    ];

    protected $casts = [
        'purchase_price'    => 'decimal:2',
        'sale_price'        => 'decimal:2',
        'minimum_stock'     => 'decimal:2',
        'is_active'         => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
