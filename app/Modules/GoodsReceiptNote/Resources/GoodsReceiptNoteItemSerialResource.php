<?php

namespace App\Modules\GoodsReceiptNote\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsReceiptNoteItemSerialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'serial_number' => $this->serial_number,
        ];
    }
}