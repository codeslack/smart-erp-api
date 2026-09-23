<?php

namespace App\Modules\Customer\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use App\Modules\PaymentTerm\Resources\PaymentTermResource;

class CustomerResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [

            'uuid' => $this->uuid,

            'name' => $this->name,
            'code' => $this->code,

            'contact_person' => $this->contact_person,

            'phone' => $this->phone,
            'email' => $this->email,

            'address' => $this->address,

            'tax_number' => $this->tax_number,

            'payment_term_id' => $this->payment_term_id,
            
            'credit_days' => $this->credit_days,
            'credit_limit' => $this->credit_limit,
            'credit_control' => $this->credit_control,

            'payment_term' => PaymentTermResource::make(
                $this->whenLoaded('paymentTerm')
            ),

            'is_active' => (bool) $this->is_active,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
