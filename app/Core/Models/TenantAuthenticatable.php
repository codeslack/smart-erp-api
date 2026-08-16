<?php

namespace App\Core\Models;

use App\Core\Scopes\TenantScope;
use App\Core\Traits\HasUuidPrimaryKey;

abstract class TenantAuthenticatable
    extends BaseAuthenticatable
{
    /**
     * Traits UUID Primary Key
     * @see https://laravel.com/docs/10.x/eloquent#custom-primary-keys
     */
    use HasUuidPrimaryKey;

    protected static function booted(): void
    {
        static::addGlobalScope(
            new TenantScope()
        );

        static::creating(function ($model) {

            if (
                empty($model->tenant_id)
                && tenant()
            ) {
                $model->tenant_id = tenantId();
            }
        });
    }
}