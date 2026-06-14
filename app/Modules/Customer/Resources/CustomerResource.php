<?php

namespace App\Modules\Customer\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [

            'id' => $this->id,

            'tenant_id' => $this->tenant_id,

            'name' => $this->name,
            'code' => $this->code,

            'contact_person' => $this->contact_person,

            'phone' => $this->phone,
            'email' => $this->email,

            'address' => $this->address,

            'tax_number' => $this->tax_number,

            'is_active' => (bool) $this->is_active,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}