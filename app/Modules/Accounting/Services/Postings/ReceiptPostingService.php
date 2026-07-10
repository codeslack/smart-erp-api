<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Modules\CustomerReceipt\Models\CustomerReceipt;
use App\Modules\Accounting\Enums\AccountingAccounts;

class ReceiptPostingService extends BasePostingService
{
    public function post(
        CustomerReceipt $receipt
    ): void {

        $receipt->loadMissing(
            'paymentAccount'
        );

        abort_if(
            !$receipt->paymentAccount,
            422,
            'Payment account not found.'
        );

        $this->createJournalEntry(

            entryDate:
                $receipt->receipt_date,

            voucherType:
                'customer_receipt',

            referenceType:
                CustomerReceipt::class,

            referenceId:
                $receipt->id,

            description:
                "Customer Receipt {$receipt->receipt_no}",

            lines: [

                [
                    'account_code' =>
                        $receipt
                            ->paymentAccount
                            ->account_code,

                    'debit' =>
                        $receipt->amount,

                    'credit' => 0,
                ],

                [
                    'account_code' =>
                        AccountingAccounts::ACCOUNTS_RECEIVABLE,

                    'debit' => 0,

                    'credit' =>
                        $receipt->amount,
                ],
            ]
        );
    }
}