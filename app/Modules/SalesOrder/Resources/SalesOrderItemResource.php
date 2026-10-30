<?php

namespace App\Modules\SalesOrder\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderItemResource extends JsonResource
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

            'product_variant' => $this->whenLoaded('productVariant', fn () => [
                'uuid' => $this->productVariant->uuid,
                'name' => $this->productVariant->name,
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

            'quantity' => (float) $this->quantity,
            'delivered_quantity' => (float) $this->delivered_quantity,
            'pending_quantity' => (float) $this->pending_quantity,
            'invoiced_quantity' => (float) $this->invoiced_quantity,
            'cancelled_quantity' => (float) $this->cancelled_quantity,

            'unit_price' => (float) $this->unit_price,
            'discount_type' => $this->discount_type,
            'discount_rate' => (float) $this->discount_rate,
            'discount_amount' => (float) $this->discount_amount,
            'net_unit_price' => (float) $this->net_unit_price,
            'subtotal_amount' => (float) $this->subtotal_amount,

            'tax_rate' => (float) $this->tax_rate,
            'is_tax_inclusive' => (bool) $this->is_tax_inclusive,
            'tax_base_amount' => (float) $this->tax_base_amount,
            'tax_total_amount' => (float) $this->tax_total_amount,
            'total_amount' => (float) $this->total_amount,
        ];
    }
}
