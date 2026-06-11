<?php

namespace App\Core\Tenant;

use App\Modules\Tenant\Models\Tenant;

class TenantResolver
{
    public function resolve(): ?Tenant
    {
        $slug = request()->header('X-Tenant');

        if (!$slug) {
            return null;
        }

        return Tenant::where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }
}