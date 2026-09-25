<?php

namespace App\Modules\Accounting\Services\Ledger;

use App\Modules\Accounting\Models\JournalEntry;

use App\Modules\Accounting\Services\Ledger\AccountLedgerRebuilder;

use App\Modules\Accounting\Repositories\Contracts\AccountLedgerRepositoryInterface;

class AccountLedgerCreator
{
    public function __construct(
        protected AccountLedgerRepositoryInterface $repository,
        protected AccountLedgerRebuilder $rebuilder,
    ) {}

    public function createFromJournal(
        JournalEntry $journalEntry
    ): void {
        $journalEntry->load([
            'lines',
        ]);

        foreach ($journalEntry->lines as $line) {
            $this->repository->create([
                'tenant_id' => $journalEntry->tenant_id,
                'chart_of_account_id' => $line->chart_of_account_id,
                'journal_entry_id' => $journalEntry->id,
                'journal_entry_line_id' => $line->id,
                'entry_date' => $journalEntry->entry_date,
                'voucher_no' => $journalEntry->voucher_no,
                'voucher_type' => $journalEntry->voucher_type,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'running_balance' => '0.0000',
                'description' => $line->description
                    ?? $journalEntry->description,
            ]);
        }

        $this->rebuilder->rebuildFromJournal(
            $journalEntry
        );
    }
}