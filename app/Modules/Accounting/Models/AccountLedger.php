<?php

namespace App\Modules\Accounting\Models;

use App\Core\Tenant\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountLedger extends TenantModel
{
    protected $fillable = [

        'tenant_id',

        'chart_of_account_id',

        'journal_entry_id',

        'journal_entry_line_id',

        'entry_date',

        'voucher_no',

        'voucher_type',

        'debit',

        'credit',

        'running_balance',

        'description',
    ];

    protected $casts = [

        'entry_date'
            => 'date',

        'debit'
            => 'decimal:4',

        'credit'
            => 'decimal:4',

        'running_balance'
            => 'decimal:4',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(
            ChartOfAccount::class,
            'chart_of_account_id'
        );
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(
            JournalEntry::class
        );
    }

    public function journalEntryLine(): BelongsTo
    {
        return $this->belongsTo(
            JournalEntryLine::class
        );
    }
}
