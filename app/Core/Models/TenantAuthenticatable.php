<?php

namespace App\Core\Models;

use App\Core\Scopes\TenantScope;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

abstract class TenantAuthenticatable
    extends Authenticatable
{
    use HasUuids;

    protected $guarded = [];

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

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}