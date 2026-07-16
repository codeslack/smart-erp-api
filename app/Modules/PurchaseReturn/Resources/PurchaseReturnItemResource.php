<?php

namespace App\Modules\PurchaseReturn\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Modules\Product\Resources\ProductResource;
use App\Modules\Warehouse\Resources\WarehouseResource;
use App\Modules\Purchase\Resources\PurchaseItemResource;

class PurchaseReturnItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(
        Request $request
    ): array {
        return [

            'id' => $this->id,

            'purchase_item_id'
                => $this->purchase_item_id,

            'product_id'
                => $this->product_id,

            'warehouse_id'
                => $this->warehouse_id,

            'quantity'
                => $this->quantity,

            'unit_cost'
                => $this->unit_cost,

            'discount'
                => $this->discount,

            'tax'
                => $this->tax,

            'line_total'
                => $this->line_total,

            'condition'
                => $this->condition?->value,

            'reason'
                => $this->reason,

            'product'
                => ProductResource::make(
                    $this->whenLoaded(
                        'product'
                    )
                ),

            'warehouse'
                => WarehouseResource::make(
                    $this->whenLoaded(
                        'warehouse'
                    )
                ),

            'purchase_item'
                => PurchaseItemResource::make(
                    $this->whenLoaded(
                        'purchaseItem'
                    )
                ),

            'created_at'
                => $this->created_at,

            'updated_at'
                => $this->updated_at,
        ];
    }
}