<?php

namespace App\Modules\Product\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
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
            | Product
            |--------------------------------------------------------------------------
            */

            'product_uuid' => $this->product?->uuid,
            'product_code'   => $this->product?->code,

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

            /*
            |--------------------------------------------------------------------------
            | Pricing Override
            |--------------------------------------------------------------------------
            */

            'purchase_price' => $this->purchase_price,

            'selling_price' => $this->selling_price,

            /*
            |--------------------------------------------------------------------------
            | Attributes
            |--------------------------------------------------------------------------
            */

            'attributes' => ProductVariantAttributeResource::collection(
                $this->whenLoaded(
                    'attributes'
                )
            ),

            'attribute_map' => $this->whenLoaded(
                'attributes',
                fn() => $this->attributes
                    ->pluck(
                        'attribute_value',
                        'attribute_name'
                    )
            ),

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
