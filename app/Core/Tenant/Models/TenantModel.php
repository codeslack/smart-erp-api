<?php

namespace App\Core\Tenant\Models;

use App\Core\Tenant\TenantScope;
use Illuminate\Database\Eloquent\Model;

abstract class TenantModel extends Model
{
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
                $model->tenant_id = tenant()->id;
            }
        });
    }
}