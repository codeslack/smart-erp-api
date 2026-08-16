<?php

namespace App\Modules\OpeningStock\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpeningStockItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(
        Request $request
    ): array {

        return [

            /*
            |--------------------------------------------------------------------------
            | Identity
            |--------------------------------------------------------------------------
            */

            'id' => $this->id,

            /*
            |--------------------------------------------------------------------------
            | Product
            |--------------------------------------------------------------------------
            */

            'product_id' => $this->product_id,

            'product' => $this->whenLoaded(
                'product',
                fn () => [
                    'id' => $this->product->id,
                    'uuid' => $this->product->uuid,
                    'code' => $this->product->code,
                    'name' => $this->product->name,
                    'sku' => $this->product->sku,
                ]
            ),

            /*
            |--------------------------------------------------------------------------
            | Variant
            |--------------------------------------------------------------------------
            */

            'product_variant_id' => $this->product_variant_id,

            'variant' => $this->whenLoaded(
                'variant',
                fn () => [
                    'id' => $this->variant->id,
                    'uuid' => $this->variant->uuid,
                    'code' => $this->variant->code,
                    'name' => $this->variant->name,
                ]
            ),

            /*
            |--------------------------------------------------------------------------
            | Batch
            |--------------------------------------------------------------------------
            */

            'product_batch_id' => $this->product_batch_id,

            'batch' => $this->whenLoaded(
                'batch',
                fn () => [
                    'id' => $this->batch->id,
                    'uuid' => $this->batch->uuid,
                    'batch_no' => $this->batch->batch_no,
                ]
            ),

            /*
            |--------------------------------------------------------------------------
            | Serial
            |--------------------------------------------------------------------------
            */

            'product_serial_id' => $this->product_serial_id,

            'serial' => $this->whenLoaded(
                'serial',
                fn () => [
                    'id' => $this->serial->id,
                    'uuid' => $this->serial->uuid,
                    'serial_number' => $this->serial->serial_number,
                ]
            ),

            /*
            |--------------------------------------------------------------------------
            | Quantity & Cost
            |--------------------------------------------------------------------------
            */

            'quantity' => $this->quantity,

            'unit_cost' => $this->unit_cost,

            'total_cost' => $this->total_cost,

            /*
            |--------------------------------------------------------------------------
            | Remarks
            |--------------------------------------------------------------------------
            */

            'remarks' => $this->remarks,
        ];
    }
}