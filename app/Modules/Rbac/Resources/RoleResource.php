<?php

namespace App\Modules\Rbac\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'uuid' => $this->uuid,

            'name' => $this->name,

            'guard_name' => $this->guard_name,

            'permissions' => PermissionResource::collection(
                $this->whenLoaded('permissions')
            ),
        ];
    }
}