<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Modules\Purchase\Models\Purchase;
use App\Modules\Accounting\Enums\AccountingAccounts;

class PurchasePostingService extends BasePostingService
{
    public function post(
        Purchase $purchase
    ): void {

        abort_if(
            $purchase->grand_total <= 0,
            422,
            'Purchase amount must be greater than zero.'
        );

        $this->createJournalEntry(

            entryDate:
                $purchase->purchase_date,

            voucherType:
                'purchase',

            referenceType:
                Purchase::class,

            referenceId:
                $purchase->id,

            description:
                "Purchase {$purchase->purchase_no}",

            lines: [

                [
                    'account_code' =>
                        AccountingAccounts::INVENTORY,

                    'debit' =>
                        $purchase->grand_total,

                    'credit' => 0,
                ],

                [
                    'account_code' =>
                        AccountingAccounts::ACCOUNTS_PAYABLE,

                    'debit' => 0,

                    'credit' =>
                        $purchase->grand_total,
                ],
            ]
        );
    }
}