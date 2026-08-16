<?php

namespace App\Modules\OpeningStock\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpeningStockResource extends JsonResource
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

            'uuid' => $this->uuid,

            /*
            |--------------------------------------------------------------------------
            | Document
            |--------------------------------------------------------------------------
            */

            'document_no' => $this->document_no,

            'status' => $this->status,

            'opening_date' => $this->opening_date,

            /*
            |--------------------------------------------------------------------------
            | Relations
            |--------------------------------------------------------------------------
            */

            'warehouse_id' => $this->warehouse_id,

            'warehouse' => $this->whenLoaded(
                'warehouse',
                fn () => [
                    'uuid' => $this->warehouse->uuid,
                    'name' => $this->warehouse->name,
                    'code' => $this->warehouse->code,
                ]
            ),

            'supplier_id' => $this->supplier_id,

            'supplier' => $this->whenLoaded(
                'supplier',
                fn () => [
                    'uuid' => $this->supplier->uuid,
                    'name' => $this->supplier->name,
                    'code' => $this->supplier->code,
                ]
            ),

            /*
            |--------------------------------------------------------------------------
            | Totals
            |--------------------------------------------------------------------------
            */

            'total_quantity' => $this->total_quantity,

            'total_amount' => $this->total_amount,

            /*
            |--------------------------------------------------------------------------
            | Remarks
            |--------------------------------------------------------------------------
            */

            'remarks' => $this->remarks,

            /*
            |--------------------------------------------------------------------------
            | Items
            |--------------------------------------------------------------------------
            */

            'items' => OpeningStockItemResource::collection(
                $this->whenLoaded(
                    'items'
                )
            ),

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