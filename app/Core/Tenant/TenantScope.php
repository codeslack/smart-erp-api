<?php

namespace App\Core\Tenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(
        Builder $builder,
        Model $model
    ): void {

        if (! tenant()) {
            return;
        }

        $builder->where(
            $model->getTable() . '.tenant_id',
            tenant()->id
        );
    }
}