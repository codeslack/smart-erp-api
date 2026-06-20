<?php

namespace App\Modules\GoodsReceiptNote\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsReceiptNoteItemResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [

            'id' => $this->id,

            'product_id' => $this->product_id,

            'warehouse_id' => $this->warehouse_id,

            'ordered_quantity' => $this->ordered_quantity,

            'received_quantity' => $this->received_quantity,

            'pending_quantity' => $this->pending_quantity,

            'unit_cost' => $this->unit_cost,

            'line_total' => $this->line_total,

        ];
    }
}
