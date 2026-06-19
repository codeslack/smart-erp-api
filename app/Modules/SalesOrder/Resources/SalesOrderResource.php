<?php

namespace App\Modules\SalesOrder\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [

            'id' => $this->id,

            'so_no' => $this->so_no,

            'customer_id' => $this->customer_id,

            'order_date' => $this->order_date,

            'subtotal' => $this->subtotal,

            'discount_amount' => $this->discount_amount,

            'tax_amount' => $this->tax_amount,

            'grand_total' => $this->grand_total,

            'status' => $this->status,

            'notes' => $this->notes,

            'items' => SalesOrderItemResource::collection(
                $this->whenLoaded(
                    'items'
                )
            ),
        ];
    }
}
