<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Modules\SalesReturn\Models\SalesReturn;
use App\Modules\Accounting\Enums\AccountingAccounts;

class SalesReturnPostingService extends BasePostingService
{
    public function post(
        SalesReturn $return
    ): void {

        $return->loadMissing([
            'items.product.stock',
        ]);

        $inventoryCost =
            $return->items->sum(
                function ($item) {

                    $averageCost =
                        $item->product?->stock?->average_cost
                        ?? 0;

                    return
                        $item->quantity
                        * $averageCost;
                }
            );

        /*
        |--------------------------------------------------------------------------
        | Reverse Revenue
        |--------------------------------------------------------------------------
        */

        $this->createJournalEntry(

            entryDate:
                $return->return_date,

            voucherType:
                'sales_return',

            referenceType:
                SalesReturn::class,

            referenceId:
                $return->id,

            description:
                "Sales Return {$return->return_no}",

            lines: [

                [
                    'account_code' =>
                        AccountingAccounts::SALES_RETURN,

                    'debit' =>
                        $return->grand_total,

                    'credit' => 0,
                ],

                [
                    'account_code' =>
                        AccountingAccounts::ACCOUNTS_RECEIVABLE,

                    'debit' => 0,

                    'credit' =>
                        $return->grand_total,
                ],
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Restore Inventory
        |--------------------------------------------------------------------------
        */
        if ($inventoryCost > 0)
        {        

            $this->createJournalEntry(

                entryDate:
                    $return->return_date,

                voucherType:
                    'sales_return',

                referenceType:
                    SalesReturn::class,

                referenceId:
                    $return->id,

                description:
                    "Inventory Restore {$return->return_no}",

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