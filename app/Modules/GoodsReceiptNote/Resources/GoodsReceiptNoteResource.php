<?php

namespace App\Modules\GoodsReceiptNote\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsReceiptNoteResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [

            'id' => $this->id,

            'grn_no' => $this->grn_no,

            'purchase_order_id' => $this->purchase_order_id,

            'supplier_id' => $this->supplier_id,

            'received_date' => $this->received_date,

            'grand_total' => $this->grand_total,

            'status' => $this->status,

            'notes' => $this->notes,

            'items' => GoodsReceiptNoteItemResource::collection(
                $this->whenLoaded(
                    'items'
                )
            ),

        ];
    }
}
