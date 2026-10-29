<?php

namespace App\Modules\Accounting\Repositories;

use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Models\AccountLedger;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Repositories\Contracts\CashFlowRepositoryInterface;

class CashFlowRepository implements CashFlowRepositoryInterface
{
    public function getCashFlow(
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        /*
        |--------------------------------------------------------------------------
        | Cash Accounts
        |--------------------------------------------------------------------------
        */
        $cashAccountIds = ChartOfAccount::query()
            ->whereIn(
                'account_code',
                [
                    AccountingAccounts::CASH,
                    AccountingAccounts::BANK,
                ]
            )
            ->pluck('id');

        /*
        |--------------------------------------------------------------------------
        | Opening Cash
        |--------------------------------------------------------------------------
        */
        $openingCash = 0;

        if ($fromDate) {
            $openingDebit = AccountLedger::query()
                ->whereIn('chart_of_account_id', $cashAccountIds)
                ->whereDate('entry_date', '<', $fromDate)
                ->sum('debit');

            $openingCredit = AccountLedger::query()
                ->whereIn('chart_of_account_id', $cashAccountIds)
                ->whereDate('entry_date', '<', $fromDate)
                ->sum('credit');

            $openingCash = $openingDebit - $openingCredit;
        }

        /*
        |--------------------------------------------------------------------------
        | Cash Transactions During Period
        |--------------------------------------------------------------------------
        */
        $cashLedgers = AccountLedger::query()
            ->where('tenant_id', tenantId())
            ->whereIn('chart_of_account_id', $cashAccountIds)
            ->when($fromDate, fn($q) => $q->whereDate('entry_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('entry_date', '<=', $toDate))
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Operating Activities
        |--------------------------------------------------------------------------
        */
        $receipts = 0;
        $payments = 0;
        $ownerCapital = 0;
        $assetPurchases = 0;

        foreach ($cashLedgers as $ledger) {
            if ($ledger->voucher_type === 'RECEIPT' || $ledger->voucher_type === 'receipt') {
                $receipts += (float) $ledger->debit;
            }

            if ($ledger->voucher_type === 'PAYMENT' || $ledger->voucher_type === 'payment') {
                $payments += (float) $ledger->credit;
            }

            if (
                ($ledger->voucher_type === 'JOURNAL' || $ledger->voucher_type === 'journal')
                && str_contains(strtolower($ledger->description ?? ''), 'capital')
            ) {
                $ownerCapital += (float) $ledger->debit;
            }
        }

        $operatingNet = $receipts - $payments;
        $investingNet = -$assetPurchases;
        $financingNet = $ownerCapital;
        $netCashFlow = $operatingNet + $investingNet + $financingNet;
        $closingCash = $openingCash + $netCashFlow;

        return [
            'operating_activities' => [
                [
                    'name'   => 'Receipts',
                    'amount' => $receipts,
                ],
                [
                    'name'   => 'Payments',
                    'amount' => -$payments,
                ],
            ],
            'investing_activities' => [
                [
                    'name'   => 'Asset Purchases',
                    'amount' => -$assetPurchases,
                ],
            ],
            'financing_activities' => [
                [
                    'name'   => 'Owner Capital',
                    'amount' => $ownerCapital,
                ],
            ],
            'opening_cash' => $openingCash,
            'net_cash_flow' => $netCashFlow,
            'closing_cash' => $closingCash,
        ];
    }
}
