<?php

namespace App\Modules\Purchase\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'purchase_no' => $this->purchase_no,

            'supplier_id' => $this->supplier_id,

            'purchase_date' => $this->purchase_date,

            'subtotal' => $this->subtotal,

            'grand_total' => $this->grand_total,

            'status' => $this->status,

            'notes' => $this->notes,

            'items' => PurchaseItemResource::collection(
                $this->whenLoaded('items')
            ),
        ];
    }
}
