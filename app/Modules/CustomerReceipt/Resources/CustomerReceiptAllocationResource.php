<?php

namespace App\Modules\CustomerReceipt\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerReceiptAllocationResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {

        return [

            'id' =>
                $this->id,

            'sale_id' =>
                $this->sale_id,

            'allocated_amount' =>
                (float) $this->allocated_amount,

            'sale' =>
                $this->whenLoaded(
                    'sale',
                    fn () => [

                        'id' =>
                            $this->sale->id,

                        'sale_no' =>
                            $this->sale->sale_no,

                        'grand_total' =>
                            (float) $this->sale->grand_total,

                        'paid_amount' =>
                            (float) $this->sale->paid_amount,

                        'due_amount' =>
                            (float) $this->sale->due_amount,

                        'status' =>
                            $this->sale->status?->value
                            ?? $this->sale->status,
                    ]
                ),
        ];
    }
}