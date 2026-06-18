<?php

namespace App\Modules\StockAdjustment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockAdjustmentResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [

            'id' => $this->id,

            'adjustment_no' => $this->adjustment_no,

            'adjustment_date' => $this->adjustment_date,

            'status' => $this->status,

            'notes' => $this->notes,

            'items' => $this->whenLoaded(
                'items'
            ),
        ];
    }
}
