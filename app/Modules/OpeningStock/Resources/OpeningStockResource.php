<?php
// :::writing{variant="document" id="90642" title="OpeningStockResource"}

namespace App\Modules\OpeningStock\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpeningStockResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'uuid' => $this->uuid,

            'document_no' => $this->document_no,

            'warehouse' => $this->whenLoaded(
                'warehouse',
                fn () => [
                    'uuid' => $this->warehouse->uuid,
                    'name' => $this->warehouse->name,
                ]
            ),

            'opening_date' => $this->opening_date?->toDateString(),

            'status' => $this->status,

            'total_quantity' => $this->total_quantity,

            'total_amount' => $this->total_amount,

            'remarks' => $this->remarks,

            'sources' => OpeningStockSourceResource::collection(
                $this->whenLoaded('sources')
            ),

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,
        ];
    }
}