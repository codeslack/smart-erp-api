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

            'uuid' => $this->uuid,

            'sku' => $this->sku,

            'barcode' => $this->barcode,

            'name' => $this->name,

            'slug' => $this->slug,

            'product_type' => $this->product_type,

            'inventory_tracking_type'
                => $this->inventory_tracking_type,

            'track_inventory'
                => $this->track_inventory,

            'track_batch'
                => $this->track_batch,

            'track_serial'
                => $this->track_serial,

            'has_expiry'
                => $this->has_expiry,

            'has_warranty'
                => $this->has_warranty,

            'requires_prescription'
                => $this->requires_prescription,

            'purchase_price'
                => $this->purchase_price,

            'selling_price'
                => $this->selling_price,

            'minimum_stock'
                => $this->minimum_stock,

            'maximum_stock'
                => $this->maximum_stock,

            'reorder_level'
                => $this->reorder_level,

            'critical_level'
                => $this->critical_level,

            'status'
                => $this->status,

            'description'
                => $this->description,

            'category' => $this->whenLoaded(
                'category'
            ),

            'brand' => $this->whenLoaded(
                'brand'
            ),

            'unit' => $this->whenLoaded(
                'unit'
            ),

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,
        ];
    }
}