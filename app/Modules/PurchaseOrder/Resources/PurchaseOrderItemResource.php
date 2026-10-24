<?php

namespace App\Modules\PurchaseOrder\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,

            'line_type' => $this->line_type,

            'product' => $this->whenLoaded('product', function () {
                return [
                    'uuid' => $this->product->uuid,
                    'name' => $this->product->name,
                    'sku' => $this->product->sku,
                ];
            }),

            'item_name' => $this->item_name,
            'sku' => $this->sku,
            'description' => $this->description,

            'warehouse' => $this->whenLoaded('warehouse', function () {
                return [
                    'uuid' => $this->warehouse->uuid,
                    'name' => $this->warehouse->name,
                ];
            }),

            'unit_of_measure' => $this->whenLoaded('unitOfMeasure', function () {
                return [
                    'uuid' => $this->unitOfMeasure->uuid,
                    'name' => $this->unitOfMeasure->name,
                    'code' => $this->unitOfMeasure->code,
                ];
            }),

            'quantity' => $this->quantity,
            'received_quantity' => $this->received_quantity,
            'billed_quantity' => $this->billed_quantity,
            'cancelled_quantity' => $this->cancelled_quantity,

            'unit_price' => $this->unit_price,

            'discount' => [
                'type' => $this->discount_type,
                'rate' => $this->discount_rate,
                'amount' => $this->discount_amount,
            ],

            'net_unit_price' => $this->net_unit_price,
            'subtotal_amount' => $this->subtotal_amount,

            'tax_group' => $this->whenLoaded('taxGroup', function () {
                return [
                    'uuid' => $this->taxGroup->uuid,
                    'name' => $this->taxGroup->name,
                ];
            }),

            'is_tax_inclusive' => $this->is_tax_inclusive,
            'tax_base_amount' => $this->tax_base_amount,
            'tax_total_amount' => $this->tax_total_amount,

            'total_amount' => $this->total_amount,
        ];
    }
}