<?php

namespace App\Core\Tenant;

use Illuminate\Foundation\Auth\User as Authenticatable;

abstract class TenantAuthenticatable extends Authenticatable
{
    protected static function booted(): void
    {
        if (! app()->runningInConsole()) {
            static::addGlobalScope(new TenantScope());
        }

        static::creating(function ($model) {

            if (
                empty($model->tenant_id)
                && tenant()
            ) {
                $model->tenant_id = tenant()->id;
            }
        });
    }
}