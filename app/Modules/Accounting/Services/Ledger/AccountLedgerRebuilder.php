<?php

namespace App\Modules\Accounting\Services\Ledger;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\BalanceCalculatorService;
use App\Modules\Accounting\Repositories\Contracts\AccountLedgerRepositoryInterface;

class AccountLedgerRebuilder
{
    public function __construct(
        protected AccountLedgerRepositoryInterface $repository,
        protected BalanceCalculatorService $balanceCalculator,
    ) {}

    public function rebuildFromJournal(
        JournalEntry $journalEntry
    ): void {
        $journalEntry->load([
            'lines.account',
        ]);

        $accountIds = $journalEntry->lines
            ->pluck('chart_of_account_id')
            ->unique()
            ->values();

        foreach ($accountIds as $accountId) {
            $account = $journalEntry->lines
                ->firstWhere(
                    'chart_of_account_id',
                    $accountId
                )
                ->account;

            if (! $account) {
                continue;
            }

            $balance = (string) (
                $this->repository->getBalanceBeforeDate(
                    tenantId: $journalEntry->tenant_id,
                    accountId: $accountId,
                    entryDate: $journalEntry->entry_date->toDateString(),
                ) ?? '0.0000'
            );

            $ledgers = $this->repository->getFromDate(
                tenantId: $journalEntry->tenant_id,
                accountId: $accountId,
                entryDate: $journalEntry->entry_date->toDateString(),
            );

            foreach ($ledgers as $ledger) {
                $balance = $this->balanceCalculator->calculate(
                    accountType: $account->account_type,
                    currentBalance: $balance,
                    debit: (string) $ledger->debit,
                    credit: (string) $ledger->credit,
                );

                $ledger->update([
                    'running_balance' => $balance,
                ]);
            }
        }
    }
}