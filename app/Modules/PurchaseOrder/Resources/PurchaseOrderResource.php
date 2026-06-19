<?php

namespace App\Modules\PurchaseOrder\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [

            'id' => $this->id,

            'po_no' => $this->po_no,

            'supplier_id' => $this->supplier_id,

            'order_date' => $this->order_date,

            'subtotal' => $this->subtotal,

            'discount_amount' => $this->discount_amount,

            'tax_amount' => $this->tax_amount,

            'grand_total' => $this->grand_total,

            'status' => $this->status,

            'notes' => $this->notes,

            'items' => PurchaseOrderItemResource::collection(
                $this->whenLoaded(
                    'items'
                )
            ),
        ];
    }
}
