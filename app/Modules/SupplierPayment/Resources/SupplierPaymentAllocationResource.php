<?php

namespace App\Modules\SupplierPayment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierPaymentAllocationResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {

        return [

            'id' =>
                $this->id,

            'purchase_id' =>
                $this->purchase_id,

            'allocated_amount' =>
                (float) $this->allocated_amount,

            'purchase' =>
                $this->whenLoaded(
                    'purchase',
                    fn () => [

                        'id' =>
                            $this->purchase->id,

                        'purchase_no' =>
                            $this->purchase->purchase_no,

                        'grand_total' =>
                            (float) $this->purchase->grand_total,

                        'paid_amount' =>
                            (float) $this->purchase->paid_amount,

                        'due_amount' =>
                            (float) $this->purchase->due_amount,

                        'status' =>
                            $this->purchase->status?->value
                            ?? $this->purchase->status,
                    ]
                ),
        ];
    }
}