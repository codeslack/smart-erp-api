<?php

namespace App\Modules\StockTransfer\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockTransferResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [

            'id' => $this->id,

            'transfer_no' => $this->transfer_no,

            'from_warehouse_id' => $this->from_warehouse_id,

            'to_warehouse_id' => $this->to_warehouse_id,

            'transfer_date' => $this->transfer_date,

            'status' => $this->status,

            'notes' => $this->notes,

            'items' => $this->whenLoaded(
                'items'
            ),
        ];
    }
}
