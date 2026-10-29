<?php

namespace App\Modules\Purchase\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'product_id' => $this->product_id,
            'product_uuid' => $this->product?->uuid,
            'product' => $this->whenLoaded('product', fn () => [
                'uuid' => $this->product->uuid,
                'name' => $this->product->name,
                'sku' => $this->product->sku ?? null,
                'product_type' => $this->product->product_type?->value ?? (string) $this->product->product_type,
            ]),
            'product_variant_id' => $this->product_variant_id,
            'product_variant_uuid' => $this->variant?->uuid,
            'variant' => $this->whenLoaded('variant', fn () => [
                'uuid' => $this->variant->uuid,
                'name' => $this->variant->name,
                'sku' => $this->variant->sku ?? null,
            ]),
            'warehouse_id' => $this->warehouse_id,
            'warehouse_uuid' => $this->warehouse?->uuid,
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'uuid' => $this->warehouse->uuid,
                'name' => $this->warehouse->name,
            ]),
            'unit_id' => $this->unit_id,
            'unit_uuid' => $this->unit?->uuid,
            'unit' => $this->whenLoaded('unit', fn () => [
                'uuid' => $this->unit->uuid,
                'name' => $this->unit->name,
                'code' => $this->unit->code ?? null,
            ]),
            'goods_receipt_note_item_id' => $this->goods_receipt_note_item_id,
            'goods_receipt_note_item_uuid' => $this->goodsReceiptNoteItem?->uuid,
            'purchase_order_item_id' => $this->purchase_order_item_id,
            'purchase_order_item_uuid' => $this->purchaseOrderItem?->uuid,
            'quantity' => (float) $this->quantity,
            'base_quantity' => $this->base_quantity !== null ? (float) $this->base_quantity : null,
            'unit_cost' => (float) $this->unit_cost,
            'line_total' => (float) $this->line_total,
            'subtotal' => (float) $this->subtotal,
            'discount_type' => $this->discount_type ?? 'none',
            'discount_rate' => (float) ($this->discount_rate ?? $this->discount_percentage ?? 0),
            'discount_percentage' => (float) ($this->discount_rate ?? $this->discount_percentage ?? 0),
            'discount_amount' => (float) $this->discount_amount,
            'tax_id' => $this->tax_id,
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => (float) $this->tax_amount,
            'total_amount' => (float) $this->total_amount,
            'returned_quantity' => (float) $this->returned_quantity,
            'available_return_quantity' => (float) $this->available_return_quantity,
            'batches' => $this->whenLoaded('batches', fn () => $this->batches->map(fn ($b) => [
                'uuid' => $b->uuid,
                'batch_no' => $b->batch_no,
                'quantity' => (float) $b->quantity,
                'base_quantity' => $b->base_quantity !== null ? (float) $b->base_quantity : null,
                'manufacturing_date' => $b->manufacturing_date?->format('Y-m-d'),
                'expiry_date' => $b->expiry_date?->format('Y-m-d'),
            ])),
            'serials' => $this->whenLoaded('serials', fn () => $this->serials->map(fn ($s) => [
                'uuid' => $s->uuid,
                'serial_number' => $s->serial_number,
            ])),
        ];
    }
}
