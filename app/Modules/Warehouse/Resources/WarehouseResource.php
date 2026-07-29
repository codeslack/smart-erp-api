<?php

namespace App\Modules\Warehouse\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {

        return [

            'id' => $this->id,

            'uuid' => $this->uuid,

            'code' => $this->code,

            'name' => $this->name,

            'area_id' => $this->area_id,

            'area_name' => $this->area?->name,

            'contact_person' => $this->contact_person,

            'phone' => $this->phone,

            'email' => $this->email,

            'address' => $this->address,

            'is_default' => $this->is_default,

            'is_active' => $this->is_active,

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,
        ];
    }
}