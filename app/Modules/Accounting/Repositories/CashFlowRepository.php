<?php

namespace App\Modules\Accounting\Repositories;

use App\Modules\Accounting\Models\AccountLedger;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Repositories\Contracts\CashFlowRepositoryInterface;

class CashFlowRepository
    implements CashFlowRepositoryInterface
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
                    '1000', // Cash
                    '1010', // Bank
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

                ->whereIn(
                    'chart_of_account_id',
                    $cashAccountIds
                )

                ->whereDate(
                    'entry_date',
                    '<',
                    $fromDate
                )

                ->sum('debit');

            $openingCredit = AccountLedger::query()

                ->whereIn(
                    'chart_of_account_id',
                    $cashAccountIds
                )

                ->whereDate(
                    'entry_date',
                    '<',
                    $fromDate
                )

                ->sum('credit');

            $openingCash =
                $openingDebit
                -
                $openingCredit;
        }

        /*
        |--------------------------------------------------------------------------
        | Cash Transactions During Period
        |--------------------------------------------------------------------------
        */

        $cashLedgers = AccountLedger::query()

            ->where(
                'tenant_id',
                tenant()->id
            )

            ->whereIn(
                'chart_of_account_id',
                $cashAccountIds
            )

            ->when(
                $fromDate,
                fn($q) =>
                $q->whereDate(
                    'entry_date',
                    '>=',
                    $fromDate
                )
            )

            ->when(
                $toDate,
                fn($q) =>
                $q->whereDate(
                    'entry_date',
                    '<=',
                    $toDate
                )
            )

            ->get();

        /*
        |--------------------------------------------------------------------------
        | Operating Activities
        |--------------------------------------------------------------------------
        */

        $customerReceipts = 0;

        $supplierPayments = 0;

        /*
        |--------------------------------------------------------------------------
        | Financing Activities
        |--------------------------------------------------------------------------
        */

        $ownerCapital = 0;

        /*
        |--------------------------------------------------------------------------
        | Investing Activities
        |--------------------------------------------------------------------------
        */

        $assetPurchases = 0;

        foreach ($cashLedgers as $ledger) {

            /*
            |--------------------------------------------------------------------------
            | Customer Receipt
            |--------------------------------------------------------------------------
            */

            if (
                $ledger->voucher_type === 'customer_receipt'
            ) {

                $customerReceipts +=
                    (float) $ledger->debit;
            }

            /*
            |--------------------------------------------------------------------------
            | Supplier Payment
            |--------------------------------------------------------------------------
            */

            if (
                $ledger->voucher_type === 'supplier_payment'
            ) {

                $supplierPayments +=
                    (float) $ledger->credit;
            }

            /*
            |--------------------------------------------------------------------------
            | Capital Introduced
            |--------------------------------------------------------------------------
            */

            if (
                $ledger->voucher_type === 'journal'
                &&
                str_contains(
                    strtolower(
                        $ledger->description ?? ''
                    ),
                    'capital'
                )
            ) {

                $ownerCapital +=
                    (float) $ledger->debit;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Totals
        |--------------------------------------------------------------------------
        */

        $operatingNet =

            $customerReceipts

            -

            $supplierPayments;

        $investingNet =

            -$assetPurchases;

        $financingNet =

            $ownerCapital;

        $netCashFlow =

            $operatingNet

            +

            $investingNet

            +

            $financingNet;

        $closingCash =

            $openingCash

            +

            $netCashFlow;

        return [

            'operating_activities' => [

                [
                    'name'   => 'Customer Receipts',
                    'amount' => $customerReceipts,
                ],

                [
                    'name'   => 'Supplier Payments',
                    'amount' => -$supplierPayments,
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

            'opening_cash'
                => $openingCash,

            'net_cash_flow'
                => $netCashFlow,

            'closing_cash'
                => $closingCash,
        ];
    }
}
