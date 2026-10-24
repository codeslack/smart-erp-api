<?php

namespace App\Core\Models;

use RuntimeException;
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
                throw new RuntimeException(
                    'Cannot create a tenant-owned record for a different tenant.'
                );
            }

            $model->tenant_id = $tenantId;
        });
    }
}