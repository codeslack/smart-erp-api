<?php

namespace App\Modules\Accounting\Models;

use App\Core\Tenant\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChartOfAccount extends TenantModel
{
    protected $table = 'chart_of_accounts';

    protected $fillable = [

        'tenant_id',

        'account_group_id',

        'parent_id',

        'account_code',

        'account_name',

        'account_type',

        'opening_balance',

        'current_balance',

        'is_system',

        'is_active',
    ];

    protected $casts = [

        'opening_balance' => 'decimal:4',

        'current_balance' => 'decimal:4',

        'is_system' => 'boolean',

        'is_active' => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(
            AccountGroup::class,
            'account_group_id'
        );
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'parent_id'
        );
    }

    public function children(): HasMany
    {
        return $this->hasMany(
            self::class,
            'parent_id'
        );
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(
            JournalEntryLine::class,
            'chart_of_account_id'
        );
    }

    public function ledgers(): HasMany
    {
        return $this->hasMany(
            AccountLedger::class,
            'chart_of_account_id'
        );
    }
}
