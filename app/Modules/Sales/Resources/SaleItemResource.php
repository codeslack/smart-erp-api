<?php

namespace App\Modules\Sales\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleItemResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,

            'product_id' => $this->product_id,

            'warehouse_id' => $this->warehouse_id,

            'quantity' => $this->quantity,

            'unit_price' => $this->unit_price,

            'line_total' => $this->line_total,
        ];
    }
}
