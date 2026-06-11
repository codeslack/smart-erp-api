<?php

namespace App\Core\Tenant\Models;

use Illuminate\Database\Eloquent\Model;

abstract class TenantModel extends Model
{
    protected static function booted()
    {
        static::creating(function ($model) {
            $model->tenant_id = tenant()->id;
        });
    }
}