<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Modules\Inventory\Models\StockLedger;
use App\Modules\SalesReturn\Models\SalesReturn;
use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Inventory\Enums\StockTransactionType;

class SalesReturnPostingService extends BasePostingService
{
    public function post(
        SalesReturn $salesReturn
    ): void {

        /*
        |--------------------------------------------------------------------------
        | Inventory Cost
        |--------------------------------------------------------------------------
        |
        | Inventory has already been restored during approval.
        | Read the actual inventory value from Stock Ledger.
        |
        */

        $inventoryCost =
            StockLedger::query()

                ->where(
                    'reference_type',
                    SalesReturn::class
                )

                ->where(
                    'reference_id',
                    $salesReturn->id
                )

                ->where(
                    'transaction_type',
                    StockTransactionType::SALES_RETURN
                )

                ->sum(
                    'line_cost'
                );

        /*
        |--------------------------------------------------------------------------
        | Reverse Revenue
        |--------------------------------------------------------------------------
        |
        | Dr Sales Return
        | Cr Accounts Receivable
        |
        */

        $this->createJournalEntry(

            entryDate:
                $salesReturn->return_date,

            voucherType:
                'sales_return',

            referenceType:
                SalesReturn::class,

            referenceId:
                $salesReturn->id,

            description:
                "Sales Return {$salesReturn->return_no}",

            lines: [

                [
                    'account_code' =>
                        AccountingAccounts::SALES_RETURN,

                    'debit' =>
                        $salesReturn->grand_total,

                    'credit' => 0,
                ],

                [
                    'account_code' =>
                        AccountingAccounts::ACCOUNTS_RECEIVABLE,

                    'debit' => 0,

                    'credit' =>
                        $salesReturn->grand_total,
                ],
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Restore Inventory
        |--------------------------------------------------------------------------
        |
        | Dr Inventory
        | Cr Cost Of Goods Sold
        |
        */

        if ($inventoryCost > 0) {

            $this->createJournalEntry(

                entryDate:
                    $salesReturn->return_date,

                voucherType:
                    'sales_return',

                referenceType:
                    SalesReturn::class,

                referenceId:
                    $salesReturn->id,

                description:
                    "Inventory Restore {$salesReturn->return_no}",

                lines: [

                    [
                        'account_code' =>
                            AccountingAccounts::INVENTORY,

                        'debit' =>
                            $inventoryCost,

                        'credit' => 0,
                    ],

                    [
                        'account_code' =>
                            AccountingAccounts::COST_OF_GOODS_SOLD,

                        'debit' => 0,

                        'credit' =>
                            $inventoryCost,
                    ],
                ]
            );
        }
    }
}
