<?php

namespace App\Modules\Accounting\Models;

use App\Core\Tenant\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryLine extends TenantModel
{
    protected $fillable = [

        'tenant_id',

        'journal_entry_id',

        'chart_of_account_id',

        'debit',
        'credit',

        'description',
    ];

    protected $casts = [

        'debit'  => 'decimal:4',
        'credit' => 'decimal:4',
    ];

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