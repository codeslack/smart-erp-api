<?php

namespace App\Modules\PurchaseOrder\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderItemResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [

            'id' => $this->id,

            'purchase_order_id' => $this->purchase_order_id,

            'product_id' => $this->product_id,

            'warehouse_id' => $this->warehouse_id,

            'quantity' => $this->quantity,

            'unit_cost' => $this->unit_cost,

            'line_total' => $this->line_total,

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,
        ];
    }
}
