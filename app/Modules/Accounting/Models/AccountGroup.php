<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

use App\Core\Models\TenantModel;

class AccountGroup extends TenantModel
{
    protected $table = 'account_groups';

    protected $fillable = [
        'name',
        'code',
    ];

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where(function ($query) use ($value, $field) {
            if ($field) {
                $query->where($field, $value);
            } elseif (Str::isUuid($value)) {
                $query->where('uuid', $value);
            } else {
                $query->where('id', $value);
            }
        })->first();
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(
            ChartOfAccount::class,
            'account_group_id'
        );
    }
}
