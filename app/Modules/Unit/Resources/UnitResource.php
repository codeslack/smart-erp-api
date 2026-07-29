<?php

namespace App\Modules\Unit\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {

        return [

            'id' => $this->id,

            'uuid' => $this->uuid,

            'code' => $this->code,

            'name' => $this->name,

            'short_name' => $this->short_name,

            'description' => $this->description,

            'is_active' => (bool) $this->is_active,

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,
        ];
    }
}