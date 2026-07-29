<?php

namespace App\Modules\Category\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [

            'id'         => $this->id,

            'uuid'       => $this->uuid,

            'code'       => $this->code,

            'name'       => $this->name,

            'description'=> $this->description,

            'is_active'  => (bool) $this->is_active,

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,
        ];
    }
}
