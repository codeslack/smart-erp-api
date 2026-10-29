<?php

namespace App\Modules\PurchaseReturn\Resources;

use App\Modules\Product\Resources\ProductResource;
use App\Modules\Purchase\Resources\PurchaseItemResource;
use App\Modules\Warehouse\Resources\WarehouseResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseReturnItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'purchase_item_uuid' => $this->purchaseItem?->uuid,
            'product_uuid' => $this->product?->uuid,
            'product_variant_uuid' => $this->variant?->uuid,
            'warehouse_uuid' => $this->warehouse?->uuid,
            'unit_uuid' => $this->unit?->uuid,
            'returned_quantity' => (float) $this->returned_quantity,
            'quantity' => (float) $this->returned_quantity,
            'base_quantity' => $this->base_quantity !== null ? (float) $this->base_quantity : null,
            'unit_cost' => (float) $this->unit_cost,
            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'discount' => (float) $this->discount_amount,
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => (float) $this->tax_amount,
            'tax' => (float) $this->tax_amount,
            'line_total' => (float) $this->line_total,
            'condition' => $this->condition instanceof \BackedEnum ? $this->condition->value : (string) $this->condition,
            'reason' => $this->reason,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'warehouse' => WarehouseResource::make($this->whenLoaded('warehouse')),
            'purchase_item' => PurchaseItemResource::make($this->whenLoaded('purchaseItem')),
            'batches' => $this->whenLoaded('batches', fn () => $this->batches->map(fn ($b) => [
                'uuid' => $b->uuid,
                'batch_no' => $b->batch_no,
                'quantity' => (float) $b->quantity,
            ])),
            'serials' => $this->whenLoaded('serials', fn () => $this->serials->map(fn ($s) => [
                'uuid' => $s->uuid,
                'serial_number' => $s->serial_number,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
