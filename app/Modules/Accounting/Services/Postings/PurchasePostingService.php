<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Core\Exceptions\BusinessException;

use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\JournalEntry\JournalEntryService;

use App\Modules\Purchase\Models\Purchase;

use App\Modules\Settings\Models\Setting;

class PurchasePostingService extends BasePostingService
{
    public function __construct(
        JournalEntryService $journalEntryService
    ) {
        parent::__construct($journalEntryService);
    }

    public function post(
        Purchase $purchase
    ): JournalEntry {
        $purchase->loadMissing([
            'charges.chartOfAccount',
        ]);

        $baseCurrency = strtoupper((string) (
            Setting::query()
                ->where('group', Setting::GROUP_COMPANY)
                ->where('key', 'currency')
                ->value('value') ?? 'INR'
        ));

        $currencyCode = strtoupper((string) (
            $purchase->currency_code ?: $baseCurrency
        ));

        $exchangeRate = $this->decimalRate(
            $purchase->exchange_rate ?? '1.00000000'
        );

        if (
            ! preg_match('/^[A-Z]{3}$/', $currencyCode)
            || $baseCurrency === ''
            || bccomp($exchangeRate, '0.00000000', 8) <= 0
        ) {
            throw new BusinessException(
                'Purchase currency and exchange rate are invalid.'
            );
        }

        if (
            $currencyCode === $baseCurrency
            && bccomp($exchangeRate, '1.00000000', 8) !== 0
        ) {
            throw new BusinessException(
                'Purchase exchange rate must be 1 when purchase currency matches company base currency.'
            );
        }

        $grandTotal = $this->decimal(
            $purchase->grand_total
        );

        $taxAmount = $this->decimal(
            $purchase->tax_amount ?? '0'
        );

        if (bccomp($taxAmount, $grandTotal, 4) > 0) {
            throw new BusinessException(
                'Purchase tax amount cannot exceed the purchase total.'
            );
        }

        $baseGrandTotal = $this->decimal(
            bcmul($grandTotal, $exchangeRate, 8)
        );

        $baseTaxAmount = $this->decimal(
            bcmul($taxAmount, $exchangeRate, 8)
        );

        $expenseCharges = '0.0000';
        $baseExpenseCharges = '0.0000';
        $lines = [];

        foreach ($purchase->charges as $charge) {
            if (
                $charge->treatment !== 'expense'
                || ! $charge->chartOfAccount
            ) {
                continue;
            }

            $chargeAmount = $this->decimal(
                $charge->amount
            );

            if (bccomp($chargeAmount, '0.0000', 4) <= 0) {
                continue;
            }

            $baseChargeAmount = $this->decimal(
                bcmul($chargeAmount, $exchangeRate, 8)
            );

            $expenseCharges = bcadd(
                $expenseCharges,
                $chargeAmount,
                4
            );

            $baseExpenseCharges = bcadd(
                $baseExpenseCharges,
                $baseChargeAmount,
                4
            );

            $lines[] = $this->debit(
                $charge->chartOfAccount->id,
                $baseChargeAmount,
                'Purchase expense charge: ' . $charge->name,
                $chargeAmount,
            );
        }

        $inventoryAmount = bcsub(
            bcsub($grandTotal, $taxAmount, 4),
            $expenseCharges,
            4
        );

        $baseInventoryAmount = bcsub(
            bcsub($baseGrandTotal, $baseTaxAmount, 4),
            $baseExpenseCharges,
            4
        );

        if (
            bccomp($inventoryAmount, '0.0000', 4) < 0
            || bccomp($baseInventoryAmount, '0.0000', 4) < 0
        ) {
            throw new BusinessException(
                'Purchase expense charges and tax cannot exceed the purchase total.'
            );
        }

        if (bccomp($baseInventoryAmount, '0.0000', 4) > 0) {
            $lines[] = $this->debit(
                AccountingAccounts::INVENTORY,
                $baseInventoryAmount,
                'Inventory purchase',
                $inventoryAmount,
            );
        }

        if (bccomp($baseTaxAmount, '0.0000', 4) > 0) {
            $lines[] = $this->debit(
                AccountingAccounts::INPUT_TAX,
                $baseTaxAmount,
                'Purchase input tax',
                $taxAmount,
            );
        }

        $lines[] = $this->credit(
            AccountingAccounts::ACCOUNTS_PAYABLE,
            $baseGrandTotal,
            'Purchase payable',
            $grandTotal,
        );

        return $this->createJournalEntry(
            referenceType: Purchase::class,
            referenceId: $purchase->id,
            voucherType: JournalVoucherTypeEnum::PURCHASE,
            entryDate: $purchase->purchase_date,
            description: 'Purchase ' . $purchase->purchase_no,
            lines: $lines,
            currencyCode: $currencyCode,
            exchangeRate: $exchangeRate,
        );
    }
}
