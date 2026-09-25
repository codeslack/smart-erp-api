<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Modules\Inventory\Models\StockLedger;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;

use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;

use App\Modules\Inventory\Enums\StockTransactionType;

class PurchaseReturnPostingService extends BasePostingService
{
    public function post(
        PurchaseReturn $purchaseReturn
    ): void {
        /*
         * Actual inventory cost returned.
         */
        $inventoryCost = StockLedger::query()
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
            ->sum('line_cost');

        $inventoryCost = $this->decimal(
            $inventoryCost
        );

        $grandTotal = $this->decimal(
            $purchaseReturn->grand_total
        );

        $this->validateAmount(
            $grandTotal
        );

        $this->validateAmount(
            $inventoryCost
        );

        /*
         * Reverse supplier liability
         *
         * Dr Accounts Payable
         * Cr Inventory
         */
        $this->createJournalEntry(
            entryDate:
                $purchaseReturn->return_date,

            voucherType:
                JournalVoucherTypeEnum
                    ::PURCHASE_RETURN
                    ->value,

            referenceType:
                PurchaseReturn::class,

            referenceId:
                $purchaseReturn->id,

            description:
                "Purchase Return {$purchaseReturn->return_no}",

            lines: [
                $this->debit(
                    AccountingAccounts::ACCOUNTS_PAYABLE,
                    $grandTotal
                ),

                $this->credit(
                    AccountingAccounts::INVENTORY,
                    $inventoryCost
                ),
            ]
        );

        /*
         * Reverse input tax.
         *
         * Dr Accounts Payable
         * Cr Input Tax Receivable
         */
        $tax = $this->decimal(
            $purchaseReturn->tax
        );

        if (
            bccomp($tax, '0.0000', 4) > 0
        ) {
            $this->createJournalEntry(
                entryDate:
                    $purchaseReturn->return_date,

                voucherType:
                    JournalVoucherTypeEnum::PURCHASE_RETURN
                        ->value,

                referenceType:
                    PurchaseReturn::class,

                referenceId:
                    $purchaseReturn->id,

                description:
                    "Purchase Return Tax {$purchaseReturn->return_no}",

                lines: [
                    $this->debit(
                        AccountingAccounts::ACCOUNTS_PAYABLE,
                        $tax
                    ),

                    $this->credit(
                        AccountingAccounts::INPUT_TAX_RECEIVABLE,
                        $tax
                    ),
                ]
            );
        }
    }
}