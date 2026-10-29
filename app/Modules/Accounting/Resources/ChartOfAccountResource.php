<?php

namespace App\Modules\Accounting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChartOfAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'account_code' => $this->account_code,
            'account_name' => $this->account_name,
            'account_type' => $this->account_type,
            'opening_balance' => (float) $this->opening_balance,
            'current_balance' => (float) $this->current_balance,
            'is_system' => (bool) $this->is_system,
            'is_active' => (bool) $this->is_active,
            'account_group_uuid' => $this->group?->uuid,
            'account_group_name' => $this->group?->name,
            'parent_account_uuid' => $this->parent?->uuid,
            'parent_account_name' => $this->parent?->account_name,
            'group' => new AccountGroupResource($this->whenLoaded('group')),
            'parent' => new ChartOfAccountResource($this->whenLoaded('parent')),
            'children' => ChartOfAccountResource::collection($this->whenLoaded('children')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
