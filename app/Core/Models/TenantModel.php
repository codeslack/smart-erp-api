<?php

namespace App\Core\Models;

use App\Core\Scopes\TenantScope;
use App\Core\Traits\HasUuidPrimaryKey;

abstract class TenantModel extends BaseModel
{
    /**
     * Traits UUID Primary Key
     * @see https://laravel.com/docs/10.x/eloquent#custom-primary-keys
     */
    use HasUuidPrimaryKey;

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
        ];
    }

    /**
     * Tenant Scope
     */
    protected static function booted(): void
    {
        static::addGlobalScope(
            new TenantScope()
        );

        static::creating(function ($model) {

            logger()->info(
                'TenantModel creating fired',
                [
                    'tenant_id' => tenantId(),
                    'model' => get_class($model),
                ]
            );

            if (! tenantId()) {

                throw new \RuntimeException(
                    'No active tenant found.'
                );
            }

            if (
                empty($model->tenant_id)
                && tenant()
            ) {
                $model->tenant_id = tenantId();
            }
        });
    }
}
