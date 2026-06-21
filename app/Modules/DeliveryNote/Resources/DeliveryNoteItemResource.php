<?php

namespace App\Modules\DeliveryNote\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryNoteItemResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [

            'id' => $this->id,

            'product_id' => $this->product_id,

            'warehouse_id' => $this->warehouse_id,

            'ordered_quantity' => $this->ordered_quantity,

            'delivered_quantity' => $this->delivered_quantity,

            'pending_quantity' => $this->pending_quantity,

            'unit_price' => $this->unit_price,

            'line_total' => $this->line_total,
        ];
    }
}
