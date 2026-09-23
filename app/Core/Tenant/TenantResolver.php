<?php

namespace App\Core\Tenant;

use App\Modules\User\Models\User;

use App\Modules\Tenant\Models\Tenant;

class TenantResolver
{
    public function resolve(): ?Tenant
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        if (! $user->tenant_id) {
            return null;
        }

        return Tenant::query()
            ->whereKey($user->tenant_id)
            ->where('is_active', true)
            ->first();
    }
}