<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Modules\SupplierPayment\Models\SupplierPayment;
use App\Modules\Accounting\Enums\AccountingAccounts;

class PaymentPostingService extends BasePostingService
{
    public function post(
        SupplierPayment $payment
    ): void {

        $payment->loadMissing(
            'paymentAccount'
        );

        abort_if(
            !$payment->paymentAccount,
            422,
            'Payment account not found.'
        );

        abort_if(
            $payment->amount <= 0,
            422,
            'Payment amount must be greater than zero.'
        );

        $this->createJournalEntry(

            entryDate:
                $payment->payment_date,

            voucherType:
                'supplier_payment',

            referenceType:
                SupplierPayment::class,

            referenceId:
                $payment->id,

            description:
                "Supplier Payment {$payment->payment_no}",

            lines: [

                [
                    'account_code' =>
                        AccountingAccounts::ACCOUNTS_PAYABLE,

                    'debit' =>
                        $payment->amount,

                    'credit' => 0,
                ],

                [
                    'account_code' =>
                        $payment
                            ->paymentAccount
                            ->account_code,

                    'debit' => 0,

                    'credit' =>
                        $payment->amount,
                ],
            ]
        );
    }
}