<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;
use App\Modules\Receipt\Models\Receipt;
use App\Modules\Receipt\Enums\ReceiptTypeEnum;

class ReceiptPostingService extends BasePostingService
{
    public function post(
        Receipt $receipt
    ): void {
        $receipt->loadMissing([
            'paymentAccount',
            'allocations',
        ]);

        $receiptAmount = $this->decimal($receipt->amount);
        $this->validateAmount($receiptAmount);

        $paymentAccountCode = $this->getAccountCode(
            $receipt->paymentAccount
        );

        $allocatedAmount = (float) $receipt->allocations->sum('allocated_amount');
        $advanceAmount = max(0, (float) $receipt->amount - $allocatedAmount);

        $lines = match ($receipt->receipt_type) {
            ReceiptTypeEnum::CUSTOMER_ADVANCE,
            ReceiptTypeEnum::OPENING_BALANCE_ADJUSTMENT => $this->buildAdvanceReceiptLines(
                paymentAccountCode: $paymentAccountCode,
                amount: $receiptAmount
            ),

            ReceiptTypeEnum::REFUND_RECEIVED => $this->buildRefundReceiptLines(
                paymentAccountCode: $paymentAccountCode,
                amount: $receiptAmount
            ),

            default => $this->buildInvoiceReceiptLines(
                paymentAccountCode: $paymentAccountCode,
                receiptAmount: $receiptAmount,
                allocatedAmount: $this->decimal($allocatedAmount),
                advanceAmount: $this->decimal($advanceAmount)
            ),
        };

        $entryDate = $receipt->receipt_date instanceof \DateTimeInterface
            ? $receipt->receipt_date->format('Y-m-d')
            : (string) $receipt->receipt_date;

        $this->createJournalEntry(
            entryDate: $entryDate,
            voucherType: JournalVoucherTypeEnum::RECEIPT->value,
            referenceType: Receipt::class,
            referenceId: $receipt->id,
            description: "Receipt {$receipt->receipt_no}",
            lines: $lines
        );
    }

    protected function buildInvoiceReceiptLines(
        string $paymentAccountCode,
        string|float $receiptAmount,
        string|float $allocatedAmount,
        string|float $advanceAmount
    ): array {
        $lines = [
            $this->debit(
                $paymentAccountCode,
                $receiptAmount
            ),
        ];

        if (bccomp((string) $allocatedAmount, '0', 4) > 0) {
            $lines[] = $this->credit(
                AccountingAccounts::ACCOUNTS_RECEIVABLE,
                $allocatedAmount
            );
        }

        if (bccomp((string) $advanceAmount, '0', 4) > 0) {
            $lines[] = $this->credit(
                AccountingAccounts::CUSTOMER_ADVANCES,
                $advanceAmount
            );
        }

        return $lines;
    }

    protected function buildAdvanceReceiptLines(
        string $paymentAccountCode,
        string|float $amount
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
        string|float $amount
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
