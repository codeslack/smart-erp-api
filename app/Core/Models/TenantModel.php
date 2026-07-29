<?php

namespace App\Core\Models;

use App\Core\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

abstract class TenantModel extends Model
{
    use HasUuids;

    /**
     * Mass Assignment
     */
    protected $guarded = [];

    /**
     * Casts
     */
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