<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\JournalEntryLine;
use App\Modules\Accounting\Repositories\Contracts\AccountLedgerRepositoryInterface;

class AccountLedgerService
{
    public function __construct(
        protected AccountLedgerRepositoryInterface $repository,
        protected BalanceCalculatorService $balanceCalculator
    ) {}

    public function createFromJournal(
        JournalEntry $journalEntry
    ): void {

        $journalEntry->load([
            'lines.account',
        ]);

        foreach ($journalEntry->lines as $line) {

            $runningBalance =
                $this->getNextRunningBalance(
                    $line
                );

            $this->repository->create([

                'tenant_id'
                    => $journalEntry->tenant_id,

                'chart_of_account_id'
                    => $line->chart_of_account_id,

                'journal_entry_id'
                    => $journalEntry->id,

                'journal_entry_line_id'
                    => $line->id,

                'entry_date'
                    => $journalEntry->entry_date,

                'voucher_no'
                    => $journalEntry->voucher_no,

                'voucher_type'
                    => $journalEntry->voucher_type,

                'debit'
                    => $line->debit,

                'credit'
                    => $line->credit,

                'running_balance'
                    => (string) $runningBalance,

                'description'
                    => $line->description
                    ?? $journalEntry->description,
            ]);
        }
    }

    protected function getNextRunningBalance(
        JournalEntryLine $line
    ): float {

        $account =
            $line->account;

        $lastBalance =
            (float) (
                $this->repository
                    ->getLastRunningBalance(
                        tenantId: $line->tenant_id,
                        accountId: $account->id
                    )
                ?? 0
            );

        return $this->balanceCalculator
            ->calculate(
                accountType:
                    $account->account_type,

                currentBalance:
                    $lastBalance,

                debit:
                    (float) $line->debit,

                credit:
                    (float) $line->credit
            );
    }
}