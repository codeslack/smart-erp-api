<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;
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

        $amount = $this->decimal($payment->amount);
        $this->validateAmount($amount);

        $paymentAccountCode = $this->getAccountCode(
            $payment->paymentAccount
        );

        $lines = match ($payment->payment_type) {
            PaymentTypeEnum::PAYMENT => $this->buildInvoicePaymentLines(
                paymentAccountCode: $paymentAccountCode,
                amount: $amount
            ),

            PaymentTypeEnum::RECEIPT => $this->buildRefundPaymentLines(
                paymentAccountCode: $paymentAccountCode,
                amount: $amount
            ),
        };

        $entryDate = $payment->payment_date instanceof \DateTimeInterface
            ? $payment->payment_date->format('Y-m-d')
            : (string) $payment->payment_date;

        $this->createJournalEntry(
            entryDate: $entryDate,
            voucherType: JournalVoucherTypeEnum::PAYMENT->value,
            referenceType: Payment::class,
            referenceId: $payment->id,
            description: "Payment {$payment->payment_no}",
            lines: $lines
        );
    }

    protected function buildInvoicePaymentLines(
        string $paymentAccountCode,
        string|float $amount
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

    protected function buildRefundPaymentLines(
        string $paymentAccountCode,
        string|float $amount
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
