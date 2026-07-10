<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Modules\PurchaseReturn\Models\PurchaseReturn;
use App\Modules\Accounting\Enums\AccountingAccounts;

class PurchaseReturnPostingService extends BasePostingService
{
    public function post(
        PurchaseReturn $return
    ): void {

        abort_if(
            $return->grand_total <= 0,
            422,
            'Purchase return amount must be greater than zero.'
        );

        $this->createJournalEntry(

            entryDate:
                $return->return_date,

            voucherType:
                'purchase_return',

            referenceType:
                PurchaseReturn::class,

            referenceId:
                $return->id,

            description:
                "Purchase Return {$return->return_no}",

            lines: [

                [
                    'account_code' =>
                        AccountingAccounts::ACCOUNTS_PAYABLE,

                    'debit' =>
                        $return->grand_total,

                    'credit' => 0,
                ],

                [
                    'account_code' =>
                        AccountingAccounts::INVENTORY,

                    'debit' => 0,

                    'credit' =>
                        $return->grand_total,
                ],
            ]
        );
    }
}