<?php

namespace App\Modules\Tenant\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {

        return [

            'id' => $this->id,

            'uuid' => $this->uuid,

            'code' => $this->code,

            'name' => $this->name,

            'slug' => $this->slug,

            'domain' => $this->domain,

            'business_type' =>
                $this->business_type?->value,

            'is_active' =>
                (bool) $this->is_active,

            'created_at' =>
                $this->created_at,

            'updated_at' =>
                $this->updated_at,
        ];
    }
}