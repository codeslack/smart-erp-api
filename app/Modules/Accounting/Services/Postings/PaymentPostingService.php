<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Modules\Accounting\Enums\AccountingAccounts;

use App\Modules\SupplierPayment\Models\SupplierPayment;
use App\Modules\SupplierPayment\Enums\SupplierPaymentType;

class PaymentPostingService extends BasePostingService
{
    public function post(
        SupplierPayment $payment
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

        $lines = match (
            $payment->payment_type
        ) {

            SupplierPaymentType::INVOICE =>
                $this->buildInvoicePaymentLines(
                    paymentAccountCode:
                        $paymentAccountCode,

                    amount:
                        $payment->amount
                ),

            SupplierPaymentType::ADVANCE =>
                $this->buildAdvancePaymentLines(
                    paymentAccountCode:
                        $paymentAccountCode,

                    amount:
                        $payment->amount
                ),

            SupplierPaymentType::REFUND =>
                $this->buildRefundPaymentLines(
                    paymentAccountCode:
                        $paymentAccountCode,

                    amount:
                        $payment->amount
                ),
        };

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

            lines:
                $lines
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
