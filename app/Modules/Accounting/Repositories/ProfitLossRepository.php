<?php

namespace App\Modules\Accounting\Repositories;

use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Repositories\Contracts\ProfitLossRepositoryInterface;

class ProfitLossRepository
    implements ProfitLossRepositoryInterface
{
    public function getProfitLoss(
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {

        $incomeAccounts = ChartOfAccount::query()

            ->where(
                'account_type',
                'income'
            )

            ->get()

            ->map(fn ($account) => [

                'account_code'
                    => $account->account_code,

                'account_name'
                    => $account->account_name,

                'amount'
                    => (float) $account->current_balance,
            ]);

        $expenseAccounts = ChartOfAccount::query()

            ->where(
                'tenant_id',
                tenant()->id
            )

            ->where(
                'account_type',
                'expense'
            )

            ->get()

            ->map(fn ($account) => [

                'account_code'
                    => $account->account_code,

                'account_name'
                    => $account->account_name,

                'amount'
                    => (float) $account->current_balance,
            ]);

        $totalIncome = $incomeAccounts
            ->sum('amount');

        $totalExpense = $expenseAccounts
            ->sum('amount');

        return [

            'income_accounts'
                => $incomeAccounts,

            'expense_accounts'
                => $expenseAccounts,

            'total_income'
                => $totalIncome,

            'total_expense'
                => $totalExpense,

            'net_profit'
                => $totalIncome - $totalExpense,
        ];
    }
}