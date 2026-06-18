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

            'grand_total' => $this->grand_total,

            'status' => $this->status,

            'notes' => $this->notes,

            'items' => $this->whenLoaded(
                'items'
            ),
        ];
    }
}
