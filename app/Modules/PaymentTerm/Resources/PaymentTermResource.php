<?php

namespace App\Modules\PaymentTerm\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentTermResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {

        return [

            'uuid' => $this->uuid,

            'code' => $this->code,

            'name' => $this->name,

            'due_days' => $this->due_days,

            'discount_days' => $this->discount_days,

            'discount_percent' => $this->discount_percent,

            'grace_days' => $this->grace_days,

            'description' => $this->description,

            'is_active' => (bool) $this->is_active,

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,
        ];
    }
}