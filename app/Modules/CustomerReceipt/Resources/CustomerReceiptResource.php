<?php

namespace App\Modules\CustomerReceipt\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerReceiptResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {

        return [

            'id' => $this->id,

            'receipt_no' => $this->receipt_no,

            'customer_id' => $this->customer_id,

            'receipt_date' => $this->receipt_date,

            'payment_method' => $this->payment_method,

            'reference_no' => $this->reference_no,

            'amount' => $this->amount,

            'status' => $this->status,

            'notes' => $this->notes,

            'allocations' =>
                CustomerReceiptAllocationResource::collection(
                    $this->whenLoaded(
                        'allocations'
                    )
                ),
        ];
    }
}