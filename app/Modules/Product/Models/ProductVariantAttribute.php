<?php

namespace App\Modules\Product\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Core\Models\BaseModel;

class ProductVariantAttribute extends BaseModel
{
    protected $table = 'product_variant_attributes';

    protected $fillable = [
        'product_variant_id',
        'attribute_name',
        'attribute_value',
    ];

    /*
    |--------------------------------------------------------------------------
    | Variant
    |--------------------------------------------------------------------------
    */

    public function variant(): BelongsTo
    {
        return $this->belongsTo(
            ProductVariant::class,
            'product_variant_id'
        );
    }
}