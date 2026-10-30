<?php

namespace App\Modules\SalesQuotation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesQuotationItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,

            'product' => $this->whenLoaded('product', fn () => [
                'uuid' => $this->product->uuid,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ]),

            'variant' => $this->whenLoaded('variant', fn () => [
                'uuid' => $this->variant->uuid,
                'name' => $this->variant->name,
            ]),

            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'uuid' => $this->warehouse->uuid,
                'name' => $this->warehouse->name,
            ]),

            'unit' => $this->whenLoaded('unit', fn () => [
                'uuid' => $this->unit->uuid,
                'name' => $this->unit->name,
                'code' => $this->unit->code,
            ]),

            'tax' => $this->whenLoaded('tax', fn () => [
                'uuid' => $this->tax->uuid,
                'name' => $this->tax->name,
                'rate' => (float) $this->tax->rate,
            ]),

            'quantity' => (float) $this->quantity,
            'unit_price' => (float) $this->unit_price,

            'discount_type' => $this->discount_type,
            'discount_rate' => (float) $this->discount_rate,
            'discount_amount' => (float) $this->discount_amount,

            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => (float) $this->tax_amount,

            'subtotal' => (float) $this->subtotal,
            'total_price' => (float) $this->total_price,
            'line_total' => (float) ($this->line_total ?? $this->total_price),
            'notes' => $this->notes,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
