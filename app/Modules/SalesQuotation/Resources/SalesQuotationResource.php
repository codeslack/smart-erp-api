<?php

namespace App\Modules\SalesQuotation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesQuotationResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [

            'id' => $this->id,

            'quotation_no' => $this->quotation_no,

            'customer_id' => $this->customer_id,

            'quotation_date' => $this->quotation_date,

            'subtotal' => $this->subtotal,

            'discount_amount' => $this->discount_amount,

            'tax_amount' => $this->tax_amount,

            'grand_total' => $this->grand_total,

            'status' => $this->status,

            'notes' => $this->notes,

            'items' => SalesQuotationItemResource::collection(
                $this->whenLoaded(
                    'items'
                )
            ),
        ];
    }
}
