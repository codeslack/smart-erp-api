<?php

namespace App\Modules\Sales\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [

            'id' => $this->id,

            'sale_no' => $this->sale_no,

            'customer_id' => $this->customer_id,

            'sale_date' => $this->sale_date,

            'subtotal' => $this->subtotal,

            'discount' => $this->discount,

            'tax' => $this->tax,

            'grand_total' => $this->grand_total,

            'paid_amount' => $this->paid_amount,

            'due_amount' => $this->due_amount,

            'status' => $this->status,

            'notes' => $this->notes,

            'advance_allocations' =>
                $this->whenLoaded(
                    'advanceAllocations',
                    fn () =>
                        $this->advanceAllocations->map(
                            fn ($allocation) => [

                                'id' =>
                                    $allocation->id,

                                'allocated_amount' =>
                                    (float) $allocation->allocated_amount,

                                'customer_receipt_id' =>
                                    $allocation->source_id,

                                'receipt_no' =>
                                    $allocation->source?->receipt_no,
                            ]
                        )
                ),

            'items' => SaleItemResource::collection(
                $this->whenLoaded('items')
            ),
        ];
    }
}