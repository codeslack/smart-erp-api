<?php

namespace App\Modules\Payment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentAllocationResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'uuid' => $this->uuid,

            'document_type' => $this->document_type,

            'document_id' => $this->document_id,

            'allocated_amount' => $this->allocated_amount,
        ];
    }
}