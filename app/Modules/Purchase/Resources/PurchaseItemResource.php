<?php

namespace App\Modules\Purchase\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            'product_id' => $this->product_id,
            
            'warehouse_id' => $this->warehouse_id,

            'quantity' => $this->quantity,

            'unit_cost' => $this->unit_cost,

            'line_total' => $this->line_total,
        ];
    }
}
