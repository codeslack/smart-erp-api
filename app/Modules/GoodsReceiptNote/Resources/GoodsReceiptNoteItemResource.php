<?php

namespace App\Modules\GoodsReceiptNote\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsReceiptNoteItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,

            'purchase_order_item_uuid' => $this->whenLoaded(
                'purchaseOrderItem',
                fn () => $this->purchaseOrderItem?->uuid
            ),

            'product' => $this->whenLoaded(
                'product',
                fn () => $this->product ? [
                    'uuid' => $this->product->uuid,
                    'name' => $this->product->name,
                ] : null
            ),

            'product_variant' => $this->whenLoaded(
                'productVariant',
                fn () => $this->productVariant ? [
                    'uuid' => $this->productVariant->uuid,
                    'name' => $this->productVariant->name,
                ] : null
            ),

            'warehouse' => $this->whenLoaded(
                'warehouse',
                fn () => $this->warehouse ? [
                    'uuid' => $this->warehouse->uuid,
                    'name' => $this->warehouse->name,
                ] : null
            ),

            'unit' => $this->whenLoaded(
                'unit',
                fn () => $this->unit ? [
                    'uuid' => $this->unit->uuid,
                    'name' => $this->unit->name,
                ] : null
            ),

            'quantity_received' => $this->quantity_received,
            'base_quantity' => $this->base_quantity,
            'unit_cost' => $this->unit_cost,

            'tax_group' => $this->whenLoaded(
                'taxGroup',
                fn () => $this->taxGroup ? [
                    'uuid' => $this->taxGroup->uuid,
                    'name' => $this->taxGroup->name,
                ] : null
            ),

            'tax_rate' => $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'line_total' => $this->line_total,
            'remarks' => $this->remarks,

            'batches' => GoodsReceiptNoteItemBatchResource::collection(
                $this->whenLoaded('batches')
            ),

            'serials' => GoodsReceiptNoteItemSerialResource::collection(
                $this->whenLoaded('serials')
            ),
        ];
    }
}