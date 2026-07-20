<?php

namespace App\Modules\Purchase\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'purchase_no' => $this->purchase_no,

            'supplier_id' => $this->supplier_id,

            'purchase_date' => $this->purchase_date,

            'subtotal' => $this->subtotal,

            'discount_amount' => $this->discount_amount,

            'tax_amount' => $this->tax_amount,

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

                                'supplier_payment_id' =>
                                    $allocation->source_id,

                                'payment_no' =>
                                    $allocation->source?->payment_no,
                            ]
                        )
                ),

            'items' => PurchaseItemResource::collection(
                $this->whenLoaded('items')
            ),
        ];
    }
}
