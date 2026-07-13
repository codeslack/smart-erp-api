<?php

namespace App\Modules\SalesReturn\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesReturnResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {

        return [

            'id' => $this->id,

            'return_no' => $this->return_no,

            'sale_id' => $this->sale_id,

            'customer_id' => $this->customer_id,

            'return_date' => $this->return_date,

            'subtotal' => $this->subtotal,

            'discount' => $this->discount,

            'tax' => $this->tax,

            'grand_total' => $this->grand_total,

            'refund_amount' => $this->refund_amount,

            'credited_amount' => $this->credited_amount,

            'refund_type' => $this->refund_type?->value,

            'return_reason' => $this->return_reason,

            'status' => $this->status,

            'approved_by' => $this->approved_by,

            'approved_at' => $this->approved_at,

            'notes' => $this->notes,

            'customer' => $this->whenLoaded(
                'customer'
            ),

            'sale' => $this->whenLoaded(
                'sale'
            ),

            'items' => SalesReturnItemResource::collection(
                $this->whenLoaded(
                    'items'
                )
            ),

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,
        ];
    }
}
