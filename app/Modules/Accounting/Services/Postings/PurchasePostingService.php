<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Core\Exceptions\BusinessException;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Settings\Models\Setting;

class PurchasePostingService extends BasePostingService
{
    public function post(Purchase $purchase): void
    {
        $grandTotal = $this->decimal($purchase->grand_total);
        $this->validateAmount($grandTotal);

        $baseCurrency = strtoupper((string) (Setting::query()
            ->where('group', Setting::GROUP_COMPANY)
            ->where('key', 'currency')
            ->value('value') ?? 'INR'));

        if (! preg_match('/^[A-Z]{3}$/', $baseCurrency)) {
            throw new BusinessException(
                'Company base currency is not configured correctly.'
            );
        }

        $currencyCode = strtoupper(
            (string) ($purchase->currency_code ?: $baseCurrency)
        );

        if (! preg_match('/^[A-Z]{3}$/', $currencyCode)) {
            throw new BusinessException(
                'Purchase currency code must be a valid three-letter code.'
            );
        }

        $exchangeRate = $this->decimalRate(
            $purchase->exchange_rate ?? '1.00000000'
        );

        if (
            $currencyCode === $baseCurrency
            && bccomp($exchangeRate, '1.00000000', 8) !== 0
        ) {
            throw new BusinessException(
                'Exchange rate must be 1 when Purchase currency matches company base currency.'
            );
        }

        $baseGrandTotal = $this->decimal(
            bcmul($grandTotal, $exchangeRate, 8)
        );

        $purchase->loadMissing(['charges.chartOfAccount']);

        $lines = [];
        $expenseChargesForeign = '0.0000';
        $expenseChargesBase = '0.0000';

        foreach ($purchase->charges as $charge) {
            $chargeForeign = $this->decimal($charge->amount);

            if (bccomp($chargeForeign, '0.0000', 4) <= 0) {
                continue;
            }

            if (
                $charge->treatment !== 'expense'
                || ! $charge->chartOfAccount
            ) {
                continue;
            }

            $chargeBase = $this->decimal(
                bcmul($chargeForeign, $exchangeRate, 8)
            );

            $expenseChargesForeign = bcadd(
                $expenseChargesForeign,
                $chargeForeign,
                4
            );

            $expenseChargesBase = bcadd(
                $expenseChargesBase,
                $chargeBase,
                4
            );

            $lines[] = [
                'chart_of_account_uuid' => $charge->chartOfAccount->uuid,
                'debit' => $chargeBase,
                'credit' => '0.0000',
                'foreign_debit' => $chargeForeign,
                'foreign_credit' => null,
                'description' => "Charge: {$charge->name}",
            ];
        }

        $taxForeign = $this->decimal($purchase->tax_amount ?? '0');
        $taxBase = $this->decimal(
            bcmul($taxForeign, $exchangeRate, 8)
        );

        $inventoryForeign = bcsub(
            bcsub($grandTotal, $taxForeign, 4),
            $expenseChargesForeign,
            4
        );

        if (bccomp($inventoryForeign, '0.0000', 4) < 0) {
            throw new BusinessException(
                'Purchase tax and expense charges cannot exceed the purchase total.'
            );
        }

        $inventoryBase = bcsub(
            bcsub($baseGrandTotal, $taxBase, 4),
            $expenseChargesBase,
            4
        );

        if (bccomp($inventoryBase, '0.0000', 4) < 0) {
            throw new BusinessException(
                'Converted purchase tax and expense charges exceed the base-currency total.'
            );
        }

        if (bccomp($inventoryForeign, '0.0000', 4) > 0) {
            $lines[] = $this->debit(
                AccountingAccounts::INVENTORY,
                $inventoryBase,
                foreignAmount: $inventoryForeign
            );
        }

        if (bccomp($taxForeign, '0.0000', 4) > 0) {
            $lines[] = $this->debit(
                AccountingAccounts::INPUT_TAX_RECEIVABLE,
                $taxBase,
                foreignAmount: $taxForeign
            );
        }

        $lines[] = $this->credit(
            AccountingAccounts::ACCOUNTS_PAYABLE,
            $baseGrandTotal,
            foreignAmount: $grandTotal
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
            exchangeRate: $exchangeRate
        );
    }
}
