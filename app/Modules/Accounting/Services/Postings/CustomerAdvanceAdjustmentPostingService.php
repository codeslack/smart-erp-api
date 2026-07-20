<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Modules\Sales\Models\Sale;

use App\Modules\Accounting\Enums\AccountingAccounts;

class CustomerAdvanceAdjustmentPostingService extends BasePostingService
{
    public function post(
        Sale $sale,
        float $adjustedAmount
    ): void {

        if (
            $adjustedAmount <= 0
        ) {
            return;
        }

        $this->createJournalEntry(

            entryDate:
                $sale->sale_date,

            voucherType:
                'customer_advance_adjustment',

            referenceType:
                Sale::class,

            referenceId:
                $sale->id,

            description:
                "Customer Advance Adjustment {$sale->sale_no}",

            lines: [

                [
                    'account_code' =>
                        AccountingAccounts::CUSTOMER_ADVANCES,

                    'debit' =>
                        $adjustedAmount,

                    'credit' => 0,
                ],

                [
                    'account_code' =>
                        AccountingAccounts::ACCOUNTS_RECEIVABLE,

                    'debit' => 0,

                    'credit' =>
                        $adjustedAmount,
                ],
            ]
        );
    }
}
