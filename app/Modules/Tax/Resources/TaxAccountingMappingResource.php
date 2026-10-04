<?php

namespace App\Modules\Tax\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxAccountingMappingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,

            'tax_component_id' => $this->tax_component_id,
            'account_id' => $this->account_id,

            'direction' => $this->direction,
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