<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Core\Models\TenantModel;

class JournalEntryLine extends TenantModel
{
    protected $table = 'journal_entry_lines';

    protected $fillable = [

        'journal_entry_id',

        'chart_of_account_id',

        'debit',
        'credit',

        'description',
    ];

    protected function casts(): array
    {
        return array_merge(
            parent::casts(),
            [
                'debit'  => 'decimal:4',
                'credit' => 'decimal:4',
            ]
        );
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(
            JournalEntry::class
        );
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(
            ChartOfAccount::class,
            'chart_of_account_id'
        );
    }
}