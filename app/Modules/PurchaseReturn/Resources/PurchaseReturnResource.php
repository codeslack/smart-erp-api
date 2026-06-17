<?php

namespace App\Modules\PurchaseReturn\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseReturnResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [

            'id' => $this->id,

            'return_no' => $this->return_no,

            'purchase_id' => $this->purchase_id,

            'supplier_id' => $this->supplier_id,

            'return_date' => $this->return_date,

            'grand_total' => $this->grand_total,

            'status' => $this->status,

            'notes' => $this->notes,

            'items' => $this->whenLoaded(
                'items'
            ),
        ];
    }
}
