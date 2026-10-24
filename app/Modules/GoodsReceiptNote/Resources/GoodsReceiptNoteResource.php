<?php

namespace App\Modules\GoodsReceiptNote\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsReceiptNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,

            'grn_number' => $this->grn_number,
            'grn_date' => $this->grn_date?->toDateString(),
            'status' => $this->status?->value,

            'purchase_order' => $this->whenLoaded(
                'purchaseOrder',
                fn () => $this->purchaseOrder ? [
                    'uuid' => $this->purchaseOrder->uuid,
                    'order_number' => $this->purchaseOrder->order_number,
                ] : null
            ),

            'supplier' => $this->whenLoaded(
                'supplier',
                fn () => $this->supplier ? [
                    'uuid' => $this->supplier->uuid,
                    'name' => $this->supplier->name,
                ] : null
            ),

            'warehouse' => $this->whenLoaded(
                'warehouse',
                fn () => $this->warehouse ? [
                    'uuid' => $this->warehouse->uuid,
                    'name' => $this->warehouse->name,
                ] : null
            ),

            'delivery_challan_number' => $this->delivery_challan_number,
            'delivery_challan_date' => $this->delivery_challan_date?->toDateString(),
            'vehicle_number' => $this->vehicle_number,
            'transporter_name' => $this->transporter_name,
            'remarks' => $this->remarks,

            'total_received_valuation' => $this->total_received_valuation,

            'items' => $this->whenLoaded(
                'items',
                fn () => GoodsReceiptNoteItemResource::collection(
                    $this->items
                )
            ),

            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}