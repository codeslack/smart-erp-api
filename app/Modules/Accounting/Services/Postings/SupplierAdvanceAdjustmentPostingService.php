<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Modules\Purchase\Models\Purchase;

use App\Modules\Accounting\Enums\AccountingAccounts;

class SupplierAdvanceAdjustmentPostingService extends BasePostingService
{
    public function post(
        Purchase $purchase,
        float $adjustedAmount
    ): void {

        if ($adjustedAmount <= 0) {
            return;
        }

        $this->createJournalEntry(

            entryDate:
                $purchase->purchase_date,

            voucherType:
                'supplier_advance_adjustment',

            referenceType:
                Purchase::class,

            referenceId:
                $purchase->id,

            description:
                "Supplier Advance Adjustment {$purchase->purchase_no}",

            lines: [

                [
                    'account_code' =>
                        AccountingAccounts::ACCOUNTS_PAYABLE,

                    'debit' =>
                        $adjustedAmount,

                    'credit' => 0,
                ],

                [
                    'account_code' =>
                        AccountingAccounts::SUPPLIER_ADVANCES,

                    'debit' => 0,

                    'credit' =>
                        $adjustedAmount,
                ],
            ]
        );
    }
}