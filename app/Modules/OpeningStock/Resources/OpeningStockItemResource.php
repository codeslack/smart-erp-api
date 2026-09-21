<?php
// :::writing{variant="document" id="41826" title="OpeningStockItemResource"}

namespace App\Modules\OpeningStock\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpeningStockItemResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'uuid' => $this->uuid,

            'product' => $this->whenLoaded(
                'product',
                fn () => $this->product
                    ? [
                        'uuid' => $this->product->uuid,
                        'name' => $this->product->name,
                        'code' => $this->product->code,
                        'sku' => $this->product->sku,
                    ]
                    : null
            ),

            'variant' => $this->whenLoaded(
                'variant',
                fn () => $this->variant
                    ? [
                        'uuid' => $this->variant->uuid,
                        'name' => $this->variant->name,
                    ]
                    : null
            ),

            'batch' => $this->whenLoaded(
                'batch',
                fn () => $this->batch
                    ? [
                        'uuid' => $this->batch->uuid,
                        'batch_no' => $this->batch->batch_no,
                    ]
                    : null
            ),

            'serial' => $this->whenLoaded(
                'serial',
                fn () => $this->serial
                    ? [
                        'uuid' => $this->serial->uuid,
                        'serial_number' =>
                            $this->serial->serial_number,
                        'imei_number' =>
                            $this->serial->imei_number,
                    ]
                    : null
            ),

            'quantity' => $this->quantity,

            'unit_cost' => $this->unit_cost,

            'total_cost' => $this->total_cost,

            'remarks' => $this->remarks,

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,
        ];
    }
}