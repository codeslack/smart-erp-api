<?php

namespace App\Core\Models;

use App\Core\Exceptions\BusinessException;
use App\Core\Scopes\TenantScope;
use App\Core\Traits\HasUuidPrimaryKey;

abstract class TenantAuthenticatable
    extends BaseAuthenticatable
{
    use HasUuidPrimaryKey;

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model): void {
            $tenantId = tenantId();

            if (! empty($model->tenant_id) && (int) $model->tenant_id !== $tenantId) {
                throw new BusinessException(
                    'Cannot create a tenant-owned record for a different tenant.',
                    'TENANT_OWNERSHIP_VIOLATION'
                );
            }

            $model->tenant_id = $tenantId;
        });

        static::updating(function ($model): void {
            $tenantId = tenant()?->getKey();

            if (! $tenantId) {
                throw new BusinessException(
                    'A tenant context is required to update tenant-owned records.',
                    'TENANT_REQUIRED'
                );
            }

            if ((int) $model->getOriginal('tenant_id') !== (int) $tenantId) {
                throw new BusinessException(
                    'Cannot update a tenant-owned record outside the active tenant.',
                    'TENANT_OWNERSHIP_VIOLATION'
                );
            }

            if ($model->isDirty('tenant_id')) {
                throw new BusinessException(
                    'Cannot change the tenant ownership of an existing record.',
                    'TENANT_OWNERSHIP_VIOLATION'
                );
            }
        });
    }
}
