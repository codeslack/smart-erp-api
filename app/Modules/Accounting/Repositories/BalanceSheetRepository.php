<?php

namespace App\Modules\Accounting\Repositories;

use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Repositories\Contracts\BalanceSheetRepositoryInterface;

class BalanceSheetRepository
implements BalanceSheetRepositoryInterface
{
    public function getBalanceSheet(
        ?string $asOfDate = null
    ): array {

        $assets = ChartOfAccount::query()

            ->where(
                'account_type',
                'asset'
            )

            ->get()

            ->map(fn($account) => [

                'account_code'
                => $account->account_code,

                'account_name'
                => $account->account_name,

                'balance'
                => (float) $account->current_balance,
            ]);

        $liabilities = ChartOfAccount::query()

            ->where(
                'account_type',
                'liability'
            )

            ->get()

            ->map(fn($account) => [

                'account_code'
                => $account->account_code,

                'account_name'
                => $account->account_name,

                'balance'
                => (float) $account->current_balance,
            ]);

        $equities = ChartOfAccount::query()

            ->where(
                'account_type',
                'equity'
            )

            ->get()

            ->map(fn($account) => [

                'account_code'
                => $account->account_code,

                'account_name'
                => $account->account_name,

                'balance'
                => (float) $account->current_balance,
            ]);

        $incomeTotal = ChartOfAccount::query()

            ->where(
                'account_type',
                'income'
            )

            ->sum('current_balance');

        $expenseTotal = ChartOfAccount::query()

            ->where(
                'account_type',
                'expense'
            )

            ->sum('current_balance');

        $netProfit =
            $incomeTotal -
            $expenseTotal;

        $totalAssets = $assets
            ->sum('balance');

        $totalLiabilities = $liabilities
            ->sum('balance');

        // $totalEquity = $equities
        //     ->sum('balance');

        // $totalLiabilitiesAndEquity =
        //     $totalLiabilities
        //     +
        //     $totalEquity
        //     +
        //     $netProfit;

        $equities->push([
            'account_code' => 'CURRENT_YEAR_PROFIT',
            'account_name' => 'Current Year Profit',
            'balance' => $netProfit,
        ]);

        $totalEquity = $equities->sum('balance');

        $totalLiabilitiesAndEquity =
            $totalLiabilities +
            $totalEquity;

        return [

            'assets' => $assets,

            'liabilities' => $liabilities,

            'equities' => $equities,

            'total_assets' => $totalAssets,

            'total_liabilities' => $totalLiabilities,

            'total_equity' => $totalEquity,

            'total_liabilities_and_equity' => $totalLiabilitiesAndEquity,

            'is_balanced'
                => bccomp(
                    (string) $totalAssets,
                    (string) $totalLiabilitiesAndEquity,
                    4
                ) === 0,
        ];
    }
}
