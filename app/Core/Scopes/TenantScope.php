<?php

namespace App\Core\Scopes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Restricts tenant-owned models to the active tenant.
 *
 * Fail closed: queries executed without a tenant context return no rows.
 * Tenant bootstrap and explicitly cross-tenant administration must use
 * non-tenant models or narrowly-scoped, documented queries instead.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = tenant()?->getKey();

        if (! $tenantId) {
            $builder->whereRaw('1 = 0');
            return;
        }

        $builder->where(
            $model->qualifyColumn('tenant_id'),
            (int) $tenantId,
        );
    }
}
