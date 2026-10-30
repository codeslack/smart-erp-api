<?php

namespace App\Modules\SalesQuotation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesQuotationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'quotation_no' => $this->quotation_no,
            'quotation_date' => $this->quotation_date?->format('Y-m-d') ?? (string) $this->quotation_date,
            'expiry_date' => $this->expiry_date?->format('Y-m-d') ?? (string) $this->expiry_date,
            'status' => $this->status,

            'customer' => $this->whenLoaded('customer', fn () => [
                'uuid' => $this->customer->uuid,
                'name' => $this->customer->name,
                'code' => $this->customer->code,
            ]),

            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'uuid' => $this->warehouse->uuid,
                'name' => $this->warehouse->name,
                'code' => $this->warehouse->code,
            ]),

            'currency_code' => $this->currency_code,
            'exchange_rate' => (float) $this->exchange_rate,

            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'tax_amount' => (float) $this->tax_amount,
            'shipping_amount' => (float) $this->shipping_amount,
            'rounding_amount' => (float) $this->rounding_amount,
            'grand_total' => (float) $this->grand_total,

            'notes' => $this->notes,
            'terms_conditions' => $this->terms_conditions,

            'converted_sales_order' => $this->whenLoaded('convertedSalesOrder', fn () => [
                'uuid' => $this->convertedSalesOrder->uuid,
                'order_number' => $this->convertedSalesOrder->order_number,
            ]),

            'converted_sale' => $this->whenLoaded('convertedSale', fn () => [
                'uuid' => $this->convertedSale->uuid,
                'sale_no' => $this->convertedSale->sale_no,
            ]),

            'sent_at' => $this->sent_at?->toISOString(),
            'accepted_at' => $this->accepted_at?->toISOString(),
            'rejected_at' => $this->rejected_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,

            'items' => SalesQuotationItemResource::collection(
                $this->whenLoaded('items')
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
