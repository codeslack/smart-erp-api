<?php

namespace App\Modules\SalesOrder\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderItemResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [

            'id' => $this->id,

            'sales_order_id' => $this->sales_order_id,

            'product_id' => $this->product_id,

            'warehouse_id' => $this->warehouse_id,

            'quantity' => $this->quantity,

            'unit_price' => $this->unit_price,

            'line_total' => $this->line_total,

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,
        ];
    }
}
