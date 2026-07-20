<?php

namespace App\Modules\SupplierPayment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierPaymentResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {

        return [

            'id' =>
                $this->id,

            'payment_no' =>
                $this->payment_no,

            'payment_date' =>
                $this->payment_date?->toDateString(),

            'payment_type' =>
                $this->payment_type?->value
                ?? $this->payment_type,

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

            'supplier' =>
                $this->whenLoaded(
                    'supplier',
                    fn () => [

                        'id' =>
                            $this->supplier->id,

                        'supplier_code' =>
                            $this->supplier->code,

                        'name' =>
                            $this->supplier->name,
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

                                'purchase_id' =>
                                    $allocation->target?->id,

                                'purchase_no' =>
                                    $allocation->target?->purchase_no,
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