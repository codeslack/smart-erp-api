<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Modules\Sales\Models\Sale;
use App\Modules\Inventory\Models\StockLedger;
use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Inventory\Enums\StockTransactionType;

class SalesPostingService extends BasePostingService
{
    public function post(
        Sale $sale
    ): void {

        $inventoryCost = StockLedger::query()
            ->where('tenant_id', tenant()->id)
            ->where(
                'reference_type',
                Sale::class
            )
            ->where(
                'reference_id',
                $sale->id
            )
            ->where(
                'transaction_type',
                StockTransactionType::SALE
            )
            ->sum('line_cost');

        /*
        |--------------------------------------------------------------------------
        | Revenue Entry
        |--------------------------------------------------------------------------
        |
        | Dr Accounts Receivable
        | Cr Sales Revenue
        |
        */

        $this->createJournalEntry(

            entryDate:
                $sale->sale_date,

            voucherType:
                'sale',

            referenceType:
                Sale::class,

            referenceId:
                $sale->id,

            description:
                "Sale {$sale->sale_no}",

            lines: [

                [
                    'account_code' =>
                        AccountingAccounts::ACCOUNTS_RECEIVABLE,

                    'debit' =>
                        $sale->grand_total,

                    'credit' => 0,
                ],

                [
                    'account_code' =>
                        AccountingAccounts::SALES_REVENUE,

                    'debit' => 0,

                    'credit' =>
                        $sale->grand_total,
                ],
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Cost Of Goods Sold Entry
        |--------------------------------------------------------------------------
        |
        | Dr Cost Of Goods Sold
        | Cr Inventory
        |
        */

        if ($inventoryCost > 0) {

            $this->createJournalEntry(

                entryDate:
                    $sale->sale_date,

                voucherType:
                    'sale',

                referenceType:
                    Sale::class,

                referenceId:
                    $sale->id,

                description:
                    "COGS {$sale->sale_no}",

                lines: [

                    [
                        'account_code' =>
                            AccountingAccounts::COST_OF_GOODS_SOLD,

                        'debit' =>
                            $inventoryCost,

                        'credit' => 0,
                    ],

                    [
                        'account_code' =>
                            AccountingAccounts::INVENTORY,

                        'debit' => 0,

                        'credit' =>
                            $inventoryCost,
                    ],
                ]
            );
        }
    }
}
