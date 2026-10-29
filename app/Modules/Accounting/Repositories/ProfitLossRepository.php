<?php

namespace App\Modules\Accounting\Repositories;

use App\Modules\Accounting\Models\AccountLedger;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Repositories\Contracts\ProfitLossRepositoryInterface;

class ProfitLossRepository implements ProfitLossRepositoryInterface
{
    public function getProfitLoss(
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $incomeAccounts = ChartOfAccount::query()
            ->where('account_type', 'income')
            ->get()
            ->map(function ($account) use ($fromDate, $toDate) {
                $amount = $this->calculateAccountPeriodBalance($account, $fromDate, $toDate);

                return [
                    'account_code' => $account->account_code,
                    'account_name' => $account->account_name,
                    'amount' => $amount,
                ];
            });

        $expenseAccounts = ChartOfAccount::query()
            ->where('account_type', 'expense')
            ->get()
            ->map(function ($account) use ($fromDate, $toDate) {
                $amount = $this->calculateAccountPeriodBalance($account, $fromDate, $toDate);

                return [
                    'account_code' => $account->account_code,
                    'account_name' => $account->account_name,
                    'amount' => $amount,
                ];
            });

        $totalIncome = (float) $incomeAccounts->sum('amount');
        $totalExpense = (float) $expenseAccounts->sum('amount');

        return [
            'income_accounts' => $incomeAccounts,
            'expense_accounts' => $expenseAccounts,
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net_profit' => $totalIncome - $totalExpense,
        ];
    }

    private function calculateAccountPeriodBalance(
        ChartOfAccount $account,
        ?string $fromDate,
        ?string $toDate
    ): float {
        if (! $fromDate && ! $toDate) {
            return (float) $account->current_balance;
        }

        $query = AccountLedger::query()->where('chart_of_account_id', $account->id);

        if ($fromDate) {
            $query->whereDate('entry_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('entry_date', '<=', $toDate);
        }

        $debits = (float) $query->sum('debit');
        $credits = (float) (clone $query)->sum('credit');

        if ($account->account_type === 'income') {
            return $credits - $debits;
        }

        return $debits - $credits;
    }
}
