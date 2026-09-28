<?php

namespace App\Modules\Payment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'uuid' => $this->uuid,

            'payment_no' => $this->payment_no,

            'payment_type' => $this->payment_type?->value,

            'payment_date' => $this->payment_date?->toDateString(),

            'party_type' => $this->party_type?->value,

            'party_id' => $this->party_id,

            'account_id' => $this->account_id,

            'amount' => $this->amount,

            'payment_method' => $this->payment_method?->value,

            'reference_no' => $this->reference_no,

            'notes' => $this->notes,

            'status' => $this->status,

            'allocations' => PaymentAllocationResource::collection(
                $this->whenLoaded('allocations')
            ),
        ];
    }
}