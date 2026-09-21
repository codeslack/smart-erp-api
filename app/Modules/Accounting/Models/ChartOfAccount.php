<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Core\Models\TenantModel;

class ChartOfAccount extends TenantModel
{
    protected $table = 'chart_of_accounts';

    protected $fillable = [

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

    protected function casts(): array
    {
        return array_merge(
            parent::casts(),
            [
                'opening_balance' => 'decimal:4',

                'current_balance' => 'decimal:4',

                'is_system' => 'boolean',

                'is_active' => 'boolean',            
            ]
        );
    }

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
