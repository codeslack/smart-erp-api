<?php
// :::writing{variant="document" id="73164" title="OpeningStockSourceResource"}

namespace App\Modules\OpeningStock\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpeningStockSourceResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'uuid' => $this->uuid,

            'supplier' => $this->whenLoaded(
                'supplier',
                fn () => $this->supplier
                    ? [
                        'uuid' => $this->supplier->uuid,
                        'name' => $this->supplier->name,
                    ]
                    : null
            ),

            'bill_no' => $this->bill_no,

            'bill_date' => $this->bill_date?->toDateString(),

            'remarks' => $this->remarks,

            'items' => OpeningStockItemResource::collection(
                $this->whenLoaded('items')
            ),

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,
        ];
    }
}