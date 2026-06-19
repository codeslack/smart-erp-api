<?php

namespace App\Modules\SalesQuotation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesQuotationItemResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [

            'id' => $this->id,

            'sales_quotation_id' => $this->sales_quotation_id,

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
