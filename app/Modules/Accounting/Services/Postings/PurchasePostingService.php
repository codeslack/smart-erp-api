<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Core\Exceptions\BusinessException;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;

class PurchasePostingService extends BasePostingService
{
    public function post(Purchase $purchase): void
    {
        $grandTotal = $this->decimal($purchase->grand_total);
        $this->validateAmount($grandTotal);

        $currency = $this->resolveCurrencyContext(
            $purchase->currency_code,
            $purchase->exchange_rate,
            'Purchase',
        );
        $currencyCode = $currency['currency_code'];
        $exchangeRate = $currency['exchange_rate'];

        $baseGrandTotal = $this->decimal(
            bcmul($grandTotal, $exchangeRate, 8)
        );

        $purchase->loadMissing(['charges.chartOfAccount']);

        $lines = [];
        $expenseChargesTotal = '0.0000';
        $baseExpenseChargesTotal = '0.0000';

        foreach ($purchase->charges as $charge) {
            $chargeAmount = $this->decimal($charge->amount);

            if (bccomp($chargeAmount, '0.0000', 4) <= 0) {
                continue;
            }

            if ($charge->treatment !== 'expense' || ! $charge->chartOfAccount) {
                continue;
            }

            $baseChargeAmount = $this->decimal(
                bcmul($chargeAmount, $exchangeRate, 8)
            );

            $expenseChargesTotal = bcadd(
                $expenseChargesTotal,
                $chargeAmount,
                4,
            );

            $baseExpenseChargesTotal = bcadd(
                $baseExpenseChargesTotal,
                $baseChargeAmount,
                4,
            );

            $lines[] = [
                'chart_of_account_uuid' => $charge->chartOfAccount->uuid,
                'debit' => $baseChargeAmount,
                'credit' => '0.0000',
                'foreign_debit' => $chargeAmount,
                'foreign_credit' => null,
                'description' => "Charge: {$charge->name}",
            ];
        }

        $taxAmount = $this->decimal($purchase->tax_amount);
        $baseTaxAmount = $this->decimal(
            bcmul($taxAmount, $exchangeRate, 8)
        );

        $inventoryAmount = bcsub(
            bcsub($grandTotal, $taxAmount, 4),
            $expenseChargesTotal,
            4,
        );

        $baseInventoryAmount = bcsub(
            bcsub($baseGrandTotal, $baseTaxAmount, 4),
            $baseExpenseChargesTotal,
            4,
        );

        if (
            bccomp($inventoryAmount, '0.0000', 4) < 0
            || bccomp($baseInventoryAmount, '0.0000', 4) < 0
        ) {
            throw new BusinessException(
                'Purchase charges and tax cannot exceed the purchase total.'
            );
        }

        if (bccomp($inventoryAmount, '0.0000', 4) > 0) {
            $lines[] = $this->debit(
                AccountingAccounts::INVENTORY,
                $baseInventoryAmount,
                foreignAmount: $inventoryAmount,
            );
        }

        if (bccomp($taxAmount, '0.0000', 4) > 0) {
            $lines[] = $this->debit(
                AccountingAccounts::INPUT_TAX_RECEIVABLE,
                $baseTaxAmount,
                foreignAmount: $taxAmount,
            );
        }

        $lines[] = $this->credit(
            AccountingAccounts::ACCOUNTS_PAYABLE,
            $baseGrandTotal,
            foreignAmount: $grandTotal,
        );

        $this->createJournalEntry(
            entryDate: $purchase->purchase_date instanceof \DateTimeInterface
                ? $purchase->purchase_date->format('Y-m-d')
                : (string) $purchase->purchase_date,
            voucherType: JournalVoucherTypeEnum::PURCHASE->value,
            referenceType: Purchase::class,
            referenceId: $purchase->id,
            description: "Purchase {$purchase->purchase_no}",
            lines: $lines,
            currencyCode: $currencyCode,
            exchangeRate: $exchangeRate,
        );
    }
}
