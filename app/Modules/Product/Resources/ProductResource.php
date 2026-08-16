<?php

namespace App\Modules\Product\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(
        Request $request
    ): array {

        return [

            // 'id' => $this->id,

            'uuid' => $this->uuid,

            /*
            |--------------------------------------------------------------------------
            | Identity
            |--------------------------------------------------------------------------
            */

            'code' => $this->code,

            'sku' => $this->sku,

            'barcode' => $this->barcode,

            'name' => $this->name,

            'display_name' => $this->display_name,

            'slug' => $this->slug,

            'description' => $this->description,

            /*
            |--------------------------------------------------------------------------
            | Classification
            |--------------------------------------------------------------------------
            */

            'product_type' => $this->product_type?->value,

            'inventory_tracking_type' => $this->inventory_tracking_type?->value,

            /*
            |--------------------------------------------------------------------------
            | Relations
            |--------------------------------------------------------------------------
            */

            'category_uuid' => $this->category?->uuid,

            'category_name' => $this->category?->name,

            'brand_uuid'    => $this->brand?->uuid,

            'brand_name'    => $this->brand?->name,

            'unit_uuid'     => $this->unit?->uuid,

            'unit_name'     => $this->unit?->name,

            'category' => $this->whenLoaded(
                'category',
                fn () => [
                    'uuid' => $this->category?->uuid,
                    'name' => $this->category?->name,
                ]
            ),

            'brand' => $this->whenLoaded(
                'brand',
                fn () => [
                    'uuid' => $this->brand?->uuid,
                    'name' => $this->brand?->name,
                ]
            ),

            'unit' => $this->whenLoaded(
                'unit',
                fn () => [
                    'uuid' => $this->unit?->uuid,
                    'name' => $this->unit?->name,
                ]
            ),

            'variants_count' => $this->whenCounted(
                'variants'
            ),

            'variants' => ProductVariantResource::collection(
                $this->whenLoaded('variants')
            ),

            /*
            |--------------------------------------------------------------------------
            | Inventory Behaviour
            |--------------------------------------------------------------------------
            */

            'has_expiry' => $this->has_expiry,

            'has_variants' => $this->has_variants,

            'has_warranty' => $this->has_warranty,

            /*
            |--------------------------------------------------------------------------
            | Pricing
            |--------------------------------------------------------------------------
            */

            'purchase_price' => $this->purchase_price,

            'selling_price' => $this->selling_price,

            /*
            |--------------------------------------------------------------------------
            | Stock Control
            |--------------------------------------------------------------------------
            */

            'minimum_stock' => $this->minimum_stock,

            'maximum_stock' => $this->maximum_stock,

            'reorder_level' => $this->reorder_level,

            'critical_level' => $this->critical_level,

            /*
            |--------------------------------------------------------------------------
            | Manufacturer
            |--------------------------------------------------------------------------
            */

            'manufacturer' => $this->manufacturer,

            'model_number' => $this->model_number,

            'part_number' => $this->part_number,

            /*
            |--------------------------------------------------------------------------
            | Physical Properties
            |--------------------------------------------------------------------------
            */

            'weight' => $this->weight,

            'length' => $this->length,

            'width' => $this->width,

            'height' => $this->height,

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            'is_active' => $this->is_active,

            /*
            |--------------------------------------------------------------------------
            | Audit
            |--------------------------------------------------------------------------
            */

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,
        ];
    }
}