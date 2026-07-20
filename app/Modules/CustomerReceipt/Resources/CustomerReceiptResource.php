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

            'id' =>
                $this->id,

            'receipt_no' =>
                $this->receipt_no,

            'receipt_date' =>
                $this->receipt_date?->toDateString(),

            'receipt_type' =>
                $this->receipt_type?->value
                ?? $this->receipt_type,

            'payment_method' =>
                $this->payment_method,

            'reference_no' =>
                $this->reference_no,

            'amount' =>
                (float) $this->amount,

            'allocated_amount' =>
                (float) $this->allocated_amount,

            'remaining_advance' =>
                (float) $this->available_advance,

            'status' =>
                $this->status?->value
                ?? $this->status,

            'notes' =>
                $this->notes,

            'customer' =>
                $this->whenLoaded(
                    'customer',
                    fn () => [

                        'id' =>
                            $this->customer->id,

                        'customer_code' =>
                            $this->customer->code,

                        'name' =>
                            $this->customer->name,
                    ]
                ),

            'payment_account' =>
                $this->whenLoaded(
                    'paymentAccount',
                    fn () => [

                        'id' =>
                            $this->paymentAccount->id,

                        'account_code' =>
                            $this->paymentAccount->account_code,

                        'account_name' =>
                            $this->paymentAccount->account_name,
                    ]
                ),

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

                                'sale_id' =>
                                    $allocation->target?->id,

                                'sale_no' =>
                                    $allocation->target?->sale_no,
                            ]
                        )
                ),

            'created_at' =>
                $this->created_at,

            'updated_at' =>
                $this->updated_at,
        ];
    }
}