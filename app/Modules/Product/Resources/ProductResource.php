<?php

namespace App\Modules\Product\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [

            'id' => $this->id,

            'tenant_id' => $this->tenant_id,

            'category_id' => $this->category_id,

            'unit_id' => $this->unit_id,

            'brand_id' => $this->brand_id,

            'name' => $this->name,

            'sku' => $this->sku,

            'barcode' => $this->barcode,

            'purchase_price' => $this->purchase_price,

            'sale_price' => $this->sale_price,

            'minimum_stock' => $this->minimum_stock,

            'description' => $this->description,

            'is_active' => $this->is_active,
        ];
    }
}