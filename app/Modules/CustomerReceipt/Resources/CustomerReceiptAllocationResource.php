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

            'id' => $this->id,

            'sale_id' => $this->sale_id,

            'allocated_amount' => $this->allocated_amount,
        ];
    }
}
