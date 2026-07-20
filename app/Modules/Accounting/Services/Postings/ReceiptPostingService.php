<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Modules\Accounting\Enums\AccountingAccounts;

use App\Modules\CustomerReceipt\Models\CustomerReceipt;
use App\Modules\CustomerReceipt\Enums\CustomerReceiptType;

class ReceiptPostingService extends BasePostingService
{
    public function post(
        CustomerReceipt $receipt
    ): void {

        $receipt->loadMissing([
            'paymentAccount',
            'allocations',
        ]);

        $this->validateAmount(
            $receipt->amount
        );

        $paymentAccountCode =
            $this->getAccountCode(
                $receipt->paymentAccount
            );

        $allocatedAmount =
            (float) $receipt
                ->allocations
                ->sum(
                    'allocated_amount'
                );

        $advanceAmount =
            max(
                0,
                $receipt->amount
                -
                $allocatedAmount
            );

        $lines = match (
            $receipt->receipt_type
        ) {

            CustomerReceiptType::INVOICE =>
                $this->buildInvoiceReceiptLines(
                    paymentAccountCode:
                        $paymentAccountCode,

                    receiptAmount:
                        $receipt->amount,

                    allocatedAmount:
                        $allocatedAmount,

                    advanceAmount:
                        $advanceAmount
                ),

            CustomerReceiptType::ADVANCE =>
                $this->buildAdvanceReceiptLines(
                    paymentAccountCode:
                        $paymentAccountCode,

                    amount:
                        $receipt->amount
                ),

            CustomerReceiptType::REFUND =>
                $this->buildRefundReceiptLines(
                    paymentAccountCode:
                        $paymentAccountCode,

                    amount:
                        $receipt->amount
                ),
        };

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

            lines:
                $lines
        );
    }

    protected function buildInvoiceReceiptLines(
        string $paymentAccountCode,
        float $receiptAmount,
        float $allocatedAmount,
        float $advanceAmount
    ): array {

        $lines = [

            $this->debit(
                $paymentAccountCode,
                $receiptAmount
            ),
        ];

        if ($allocatedAmount > 0) {

            $lines[] =
                $this->credit(
                    AccountingAccounts::ACCOUNTS_RECEIVABLE,
                    $allocatedAmount
                );
        }

        if ($advanceAmount > 0) {

            $lines[] =
                $this->credit(
                    AccountingAccounts::CUSTOMER_ADVANCES,
                    $advanceAmount
                );
        }

        return $lines;
    }

    protected function buildAdvanceReceiptLines(
        string $paymentAccountCode,
        float $amount
    ): array {

        return [

            $this->debit(
                $paymentAccountCode,
                $amount
            ),

            $this->credit(
                AccountingAccounts::CUSTOMER_ADVANCES,
                $amount
            ),
        ];
    }

    protected function buildRefundReceiptLines(
        string $paymentAccountCode,
        float $amount
    ): array {

        return [

            $this->debit(
                AccountingAccounts::CUSTOMER_ADVANCES,
                $amount
            ),

            $this->credit(
                $paymentAccountCode,
                $amount
            ),
        ];
    }
}
