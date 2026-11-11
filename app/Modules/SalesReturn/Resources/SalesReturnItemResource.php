<?php

namespace App\Modules\SalesReturn\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesReturnItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'line_type' => $this->line_type,
            'item_name' => $this->item_name,
            'sku' => $this->sku,
            'description' => $this->description,

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

            'sale_item' => $this->whenLoaded('saleItem', fn () => [
                'uuid' => $this->saleItem->uuid,
                'unit_price' => (float) $this->saleItem->unit_price,
            ]),

            'returned_quantity' => (float) $this->returned_quantity,
            'base_quantity' => $this->base_quantity !== null ? (float) $this->base_quantity : null,
            'quantity' => (float) $this->returned_quantity,
            'unit_price' => (float) $this->unit_price,
            'unit_cost' => (float) $this->unit_cost,

            'discount_type' => $this->discount_type,
            'discount_rate' => (float) $this->discount_rate,
            'discount_amount' => (float) $this->discount_amount,

            'net_unit_price' => (float) $this->net_unit_price,
            'subtotal' => (float) $this->subtotal,

            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => (float) $this->tax_amount,

            'line_total' => (float) $this->line_total,
            'total_amount' => (float) $this->total_amount,

            'condition' => $this->condition?->value ?? (string) $this->condition,
            'reason' => $this->reason,

            'batches' => $this->whenLoaded('batches', fn () => $this->batches->map(fn ($b) => [
                'uuid' => $b->uuid,
                'batch_no' => $b->batch_no,
                'quantity' => (float) $b->quantity,
                'base_quantity' => $b->base_quantity !== null ? (float) $b->base_quantity : (float) $b->quantity,
            ])),

            'serials' => $this->whenLoaded('serials', fn () => $this->serials->map(fn ($s) => [
                'uuid' => $s->uuid,
                'serial_number' => $s->serial_number,
            ])),
        ];
    }
}
