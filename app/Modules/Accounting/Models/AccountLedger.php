<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Core\Models\TenantModel;

class AccountLedger extends TenantModel
{
    protected $table = 'account_ledgers';

    protected $fillable = [

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

    protected function casts(): array
    {
        return array_merge(
            parent::casts(),
            [
                'entry_date'
                    => 'date',

                'debit'
                    => 'decimal:4',

                'credit'
                    => 'decimal:4',

                'running_balance'
                    => 'decimal:4',
            ]
        );
    }

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
