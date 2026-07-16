<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Modules\Inventory\Models\StockLedger;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;
use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Inventory\Enums\StockTransactionType;

class PurchaseReturnPostingService extends BasePostingService
{
    public function post(
        PurchaseReturn $purchaseReturn
    ): void {

        /*
        |--------------------------------------------------------------------------
        | Actual Inventory Cost
        |--------------------------------------------------------------------------
        */

        $inventoryCost =
            StockLedger::query()

                ->where(
                    'reference_type',
                    PurchaseReturn::class
                )

                ->where(
                    'reference_id',
                    $purchaseReturn->id
                )

                ->where(
                    'transaction_type',
                    StockTransactionType::PURCHASE_RETURN
                )

                ->sum(
                    'line_cost'
                );

        /*
        |--------------------------------------------------------------------------
        | Reverse Supplier Liability
        |--------------------------------------------------------------------------
        |
        | Dr Accounts Payable
        | Cr Inventory
        |
        */

        $this->createJournalEntry(

            entryDate:
                $purchaseReturn->return_date,

            voucherType:
                'purchase_return',

            referenceType:
                PurchaseReturn::class,

            referenceId:
                $purchaseReturn->id,

            description:
                "Purchase Return {$purchaseReturn->return_no}",

            lines: [

                [
                    'account_code' =>
                        AccountingAccounts::ACCOUNTS_PAYABLE,

                    'debit' =>
                        $purchaseReturn->grand_total,

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

        /*
        |--------------------------------------------------------------------------
        | Reverse Input Tax
        |--------------------------------------------------------------------------
        */

        if (
            $purchaseReturn->tax > 0
        ) {

            $this->createJournalEntry(

                entryDate:
                    $purchaseReturn->return_date,

                voucherType:
                    'purchase_return',

                referenceType:
                    PurchaseReturn::class,

                referenceId:
                    $purchaseReturn->id,

                description:
                    "Purchase Return Tax {$purchaseReturn->return_no}",

                lines: [

                    [
                        'account_code' =>
                            AccountingAccounts::ACCOUNTS_PAYABLE,

                        'debit' =>
                            $purchaseReturn->tax,

                        'credit' => 0,
                    ],

                    [
                        'account_code' =>
                            AccountingAccounts::INPUT_TAX_RECEIVABLE,

                        'debit' => 0,

                        'credit' =>
                            $purchaseReturn->tax,
                    ],
                ]
            );
        }
    }
}