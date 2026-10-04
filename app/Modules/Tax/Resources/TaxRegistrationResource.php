<?php

namespace App\Modules\Tax\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxRegistrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,

            'registrable_type' => $this->registrable_type,
            'registrable_id' => $this->registrable_id,

            'registration_type' =>
                $this->registration_type,

            'registration_number' =>
                $this->registration_number,

            'country_code' =>
                $this->country_code,

            'state_code' =>
                $this->state_code,

            'is_primary' =>
                $this->is_primary,

            'is_active' =>
                $this->is_active,

            'effective_from' =>
                $this->effective_from?->toDateString(),

            'effective_to' =>
                $this->effective_to?->toDateString(),

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }
}