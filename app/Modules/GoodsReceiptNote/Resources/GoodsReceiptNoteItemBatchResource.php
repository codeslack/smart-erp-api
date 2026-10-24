<?php

namespace App\Modules\GoodsReceiptNote\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsReceiptNoteItemBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,

            'batch_no' => $this->batch_no,
            'quantity' => $this->quantity,
            'expiry_date' => $this->expiry_date?->toDateString(),
        ];
    }
}