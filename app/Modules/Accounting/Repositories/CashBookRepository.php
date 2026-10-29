<?php

namespace App\Modules\Accounting\Repositories;

use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Models\AccountLedger;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Repositories\Contracts\CashBookRepositoryInterface;

class CashBookRepository implements CashBookRepositoryInterface
{
    public function getCashBook(
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $cashAccount = ChartOfAccount::query()
            ->where('account_code', AccountingAccounts::CASH)
            ->firstOrFail();

        $openingBalance = 0.0;

        if ($fromDate) {
            $openingDebit = (float) AccountLedger::query()
                ->where('chart_of_account_id', $cashAccount->id)
                ->whereDate('entry_date', '<', $fromDate)
                ->sum('debit');

            $openingCredit = (float) AccountLedger::query()
                ->where('chart_of_account_id', $cashAccount->id)
                ->whereDate('entry_date', '<', $fromDate)
                ->sum('credit');

            $openingBalance = $openingDebit - $openingCredit;
        }

        $ledgers = AccountLedger::query()
            ->where('chart_of_account_id', $cashAccount->id)
            ->when($fromDate, fn($q) => $q->whereDate('entry_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('entry_date', '<=', $toDate))
            ->orderBy('entry_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $runningBalance = $openingBalance;

        $transactions = $ledgers->map(function ($ledger) use (&$runningBalance) {
            $runningBalance += (float) $ledger->debit;
            $runningBalance -= (float) $ledger->credit;

            return [
                'date' => $ledger->entry_date,
                'voucher_no' => $ledger->voucher_no,
                'description' => $ledger->description,
                'debit' => (float) $ledger->debit,
                'credit' => (float) $ledger->credit,
                'balance' => $runningBalance,
            ];
        });

        return [
            'opening_balance' => $openingBalance,
            'transactions' => $transactions,
            'closing_balance' => $runningBalance,
        ];
    }
}
