<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Core\Models\TenantModel;

class AccountGroup extends TenantModel
{
    protected $table = 'account_groups';

    protected $fillable = [
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