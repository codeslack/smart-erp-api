<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\AccountLedger;
use App\Modules\Accounting\Models\JournalEntryLine;
use App\Modules\Accounting\Repositories\Contracts\AccountLedgerRepositoryInterface;

class AccountLedgerService
{
    public function __construct(
        protected AccountLedgerRepositoryInterface $repository
    ) {}

    public function createFromJournal(
        JournalEntry $journalEntry
    ): void {

        $journalEntry->load([
            'lines.account'
        ]);

        foreach (
            $journalEntry->lines as $line
        ) {

            $runningBalance = $this->calculateRunningBalance(
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
                    => $runningBalance,

                'description'
                    => $line->description
                        ?? $journalEntry->description,
            ]);
        }
    }

    protected function calculateRunningBalance(
        JournalEntryLine $line
    ): string {

        $account = $line->account;

        $lastBalance = AccountLedger::query()

            ->where(
                'tenant_id',
                $line->tenant_id
            )

            ->where(
                'chart_of_account_id',
                $account->id
            )

            ->latest('id')

            ->value(
                'running_balance'
            ) ?? 0;

        $balance = $lastBalance;

        switch (
            $account->account_type
        ) {

            case AccountType::ASSET:

            case AccountType::EXPENSE:

                // $balance += $line->debit;
                $balance = bcadd(
                    (string) $balance,
                    (string) $line->debit,
                    4
                );
                
                // $balance -= $line->credit;
                $balance = bcsub(
                    (string) $balance,
                    (string) $line->credit,
                    4
                );

                break;

            case AccountType::LIABILITY:

            case AccountType::EQUITY:

            case AccountType::INCOME:

                // $balance -= $line->debit;
                $balance = bcsub(
                    (string) $balance,
                    (string) $line->debit,
                    4
                );

                // $balance += $line->credit;
                $balance = bcadd(
                    (string) $balance,
                    (string) $line->credit,
                    4
                );

                break;
        }

        return (string) $balance;
    }
}
