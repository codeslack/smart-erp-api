<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\Ledger\AccountLedgerCreator;

class AccountLedgerService
{
    public function __construct(
        protected AccountLedgerCreator $creator,
    ) {}

    public function createFromJournal(
        JournalEntry $journalEntry
    ): void {
        $this->creator->createFromJournal(
            $journalEntry
        );
    }
}