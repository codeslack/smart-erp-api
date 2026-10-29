<?php

namespace App\Modules\Accounting\Repositories;

use App\Modules\Accounting\Models\AccountLedger;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Repositories\Contracts\BalanceSheetRepositoryInterface;

class BalanceSheetRepository implements BalanceSheetRepositoryInterface
{
    public function getBalanceSheet(
        ?string $asOfDate = null
    ): array {
        $assets = ChartOfAccount::query()
            ->where('account_type', 'asset')
            ->get()
            ->map(fn($account) => [
                'account_code' => $account->account_code,
                'account_name' => $account->account_name,
                'balance' => $this->calculateBalanceAsOf($account, $asOfDate),
            ]);

        $liabilities = ChartOfAccount::query()
            ->where('account_type', 'liability')
            ->get()
            ->map(fn($account) => [
                'account_code' => $account->account_code,
                'account_name' => $account->account_name,
                'balance' => $this->calculateBalanceAsOf($account, $asOfDate),
            ]);

        $equities = ChartOfAccount::query()
            ->where('account_type', 'equity')
            ->get()
            ->map(fn($account) => [
                'account_code' => $account->account_code,
                'account_name' => $account->account_name,
                'balance' => $this->calculateBalanceAsOf($account, $asOfDate),
            ]);

        $incomeTotal = ChartOfAccount::query()
            ->where('account_type', 'income')
            ->get()
            ->sum(fn($account) => $this->calculateBalanceAsOf($account, $asOfDate));

        $expenseTotal = ChartOfAccount::query()
            ->where('account_type', 'expense')
            ->get()
            ->sum(fn($account) => $this->calculateBalanceAsOf($account, $asOfDate));

        $netProfit = $incomeTotal - $expenseTotal;

        $totalAssets = (float) $assets->sum('balance');
        $totalLiabilities = (float) $liabilities->sum('balance');

        $equities->push([
            'account_code' => 'CURRENT_YEAR_PROFIT',
            'account_name' => 'Current Year Profit',
            'balance' => $netProfit,
        ]);

        $totalEquity = (float) $equities->sum('balance');
        $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquity;

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equities' => $equities,
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity' => $totalEquity,
            'total_liabilities_and_equity' => $totalLiabilitiesAndEquity,
            'is_balanced' => bccomp(
                (string) $totalAssets,
                (string) $totalLiabilitiesAndEquity,
                4
            ) === 0,
        ];
    }

    private function calculateBalanceAsOf(ChartOfAccount $account, ?string $asOfDate): float
    {
        if (! $asOfDate) {
            return (float) $account->current_balance;
        }

        $debits = (float) AccountLedger::query()
            ->where('chart_of_account_id', $account->id)
            ->whereDate('entry_date', '<=', $asOfDate)
            ->sum('debit');

        $credits = (float) AccountLedger::query()
            ->where('chart_of_account_id', $account->id)
            ->whereDate('entry_date', '<=', $asOfDate)
            ->sum('credit');

        if (in_array($account->account_type, ['asset', 'expense'], true)) {
            return $debits - $credits;
        }

        return $credits - $debits;
    }
}
