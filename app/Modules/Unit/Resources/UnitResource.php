<?php

namespace App\Modules\Unit\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'is_active' => (bool) $this->is_active,
        ];
    }
}