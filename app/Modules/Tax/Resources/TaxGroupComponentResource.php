<?php

namespace App\Modules\Tax\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxGroupComponentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,

            'tax_group_id' => $this->tax_group_id,
            'tax_component_id' => $this->tax_component_id,

            'rate' => $this->rate,
            'calculation_method' => $this->calculation_method,

            'is_active' => $this->is_active,

            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),

            'sort_order' => $this->sort_order,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}