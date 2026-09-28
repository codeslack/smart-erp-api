<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Modules\Accounting\Enums\AccountingAccounts;

use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Enums\PaymentTypeEnum;

class PaymentPostingService extends BasePostingService
{
    public function post(
        Payment $payment
    ): void {

        $payment->loadMissing(
            'paymentAccount'
        );

        $this->validateAmount(
            $payment->amount
        );

        $paymentAccountCode =
            $this->getAccountCode(
                $payment->paymentAccount
            );

        $lines = match ($payment->payment_type) {

            PaymentTypeEnum::INVOICE =>
            $this->buildInvoicePaymentLines(
                paymentAccountCode: $paymentAccountCode,

                amount: $payment->amount
            ),

            PaymentTypeEnum::ADVANCE =>
            $this->buildAdvancePaymentLines(
                paymentAccountCode: $paymentAccountCode,

                amount: $payment->amount
            ),

            PaymentTypeEnum::REFUND =>
            $this->buildRefundPaymentLines(
                paymentAccountCode: $paymentAccountCode,

                amount: $payment->amount
            ),
        };

        $this->createJournalEntry(

            entryDate: $payment->payment_date,

            voucherType: 'payment',

            referenceType: Payment::class,

            referenceId: $payment->id,

            description: "Payment {$payment->payment_no}",

            lines: $lines
        );
    }

    protected function buildInvoicePaymentLines(
        string $paymentAccountCode,
        float $amount
    ): array {

        return [

            $this->debit(
                AccountingAccounts::ACCOUNTS_PAYABLE,
                $amount
            ),

            $this->credit(
                $paymentAccountCode,
                $amount
            ),
        ];
    }

    protected function buildAdvancePaymentLines(
        string $paymentAccountCode,
        float $amount
    ): array {

        return [

            $this->debit(
                AccountingAccounts::SUPPLIER_ADVANCES,
                $amount
            ),

            $this->credit(
                $paymentAccountCode,
                $amount
            ),
        ];
    }

    protected function buildRefundPaymentLines(
        string $paymentAccountCode,
        float $amount
    ): array {

        return [

            $this->debit(
                $paymentAccountCode,
                $amount
            ),

            $this->credit(
                AccountingAccounts::SUPPLIER_ADVANCES,
                $amount
            ),
        ];
    }
}
