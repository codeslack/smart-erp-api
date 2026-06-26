<?php

namespace App\Modules\Accounting\Models;

use App\Core\Tenant\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountGroup extends TenantModel
{
    protected $table = 'account_groups';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
    ];

    public function accounts(): HasMany
    {
        return $this->hasMany(
            ChartOfAccount::class,
            'account_group_id'
        );
    }
}