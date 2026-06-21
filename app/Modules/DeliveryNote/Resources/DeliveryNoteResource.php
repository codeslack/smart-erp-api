<?php

namespace App\Modules\DeliveryNote\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryNoteResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [

            'id' => $this->id,

            'dn_no' => $this->dn_no,

            'sales_order_id' => $this->sales_order_id,

            'customer_id' => $this->customer_id,

            'delivery_date' => $this->delivery_date,

            'grand_total' => $this->grand_total,

            'status' => $this->status,

            'notes' => $this->notes,

            'items' => DeliveryNoteItemResource::collection(
                $this->whenLoaded(
                    'items'
                )
            ),
        ];
    }
}
