<?php

namespace App\Modules\Tax\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxApplicabilityRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,

            'tax_group_id' => $this->tax_group_id,

            'source_country_code' => $this->source_country_code,
            'source_state_code' => $this->source_state_code,

            'destination_country_code' =>
                $this->destination_country_code,

            'destination_state_code' =>
                $this->destination_state_code,

            'place_of_supply_state_code' =>
                $this->place_of_supply_state_code,

            'source_registration_required' =>
                $this->source_registration_required,

            'destination_registration_required' =>
                $this->destination_registration_required,

            'priority' => $this->priority,
            'is_active' => $this->is_active,

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