<?php

namespace App\Modules\SalesReturn\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesReturnItemResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {

        return [

            'id' => $this->id,

            'sale_item_id' =>
                $this->sale_item_id,

            'product_id' =>
                $this->product_id,

            'warehouse_id' =>
                $this->warehouse_id,

            'quantity' =>
                $this->quantity,

            'unit_price' =>
                $this->unit_price,

            'discount' =>
                $this->discount,

            'tax' =>
                $this->tax,

            'line_total' =>
                $this->line_total,

            'condition' =>
                $this->condition,

            'reason' =>
                $this->reason,

            'product' => $this->whenLoaded(
                'product'
            ),

            'warehouse' => $this->whenLoaded(
                'warehouse'
            ),

            'sale_item' => $this->whenLoaded(
                'saleItem'
            ),

            'created_at' =>
                $this->created_at,

            'updated_at' =>
                $this->updated_at,
        ];
    }
}
