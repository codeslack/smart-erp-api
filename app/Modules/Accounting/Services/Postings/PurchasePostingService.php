<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Core\Exceptions\BusinessException;

use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;
use App\Modules\Accounting\Services\JournalEntry\JournalEntryService;

use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Services\PurchaseInventoryValuationService;

class PurchasePostingService extends BasePostingService
{
    public function __construct(
        JournalEntryService $journalEntryService,
        protected PurchaseInventoryValuationService $valuationService,
    ) {
        parent::__construct($journalEntryService);
    }

    public function post(
        Purchase $purchase
    ): void {
        $valuation = $this->valuationService->calculate(
            $purchase
        );

        $currencyCode = $valuation['currency_code'];
        $exchangeRate = $valuation['exchange_rate'];

        $grandTotal = $valuation['grand_total'];
        $taxAmount = $valuation['tax_amount'];
        $stockAmount = $valuation['stock_amount'];
        $nonStockAmount = $valuation['non_stock_amount'];

        $baseGrandTotal = $this->decimal(
            bcmul($grandTotal, $exchangeRate, 8)
        );

        $baseTaxAmount = $this->decimal(
            bcmul($taxAmount, $exchangeRate, 8)
        );

        $lines = [];

        foreach ($purchase->charges as $charge) {
            if ($charge->treatment !== 'expense') {
                continue;
            }

            if (! $charge->chartOfAccount) {
                throw new BusinessException(
                    'Every expense purchase charge must have an active expense account.'
                );
            }

            $chargeAmount = $this->decimal($charge->amount);
            $baseChargeAmount = $this->decimal(
                bcmul($chargeAmount, $exchangeRate, 8)
            );

            if (bccomp($chargeAmount, '0.0000', 4) <= 0) {
                continue;
            }

            $lines[] = $this->debit(
                $charge->chartOfAccount->account_code,
                $baseChargeAmount,
                'Purchase expense charge: ' . $charge->name,
                $chargeAmount,
            );
        }

        if (bccomp($stockAmount, '0.0000', 4) > 0) {
            $lines[] = $this->debit(
                AccountingAccounts::INVENTORY,
                $this->decimal(bcmul($stockAmount, $exchangeRate, 8)),
                'Inventory purchase',
                $stockAmount,
            );
        }

        if (bccomp($nonStockAmount, '0.0000', 4) > 0) {
            $lines[] = $this->debit(
                AccountingAccounts::PURCHASE_EXPENSE,
                $this->decimal(bcmul($nonStockAmount, $exchangeRate, 8)),
                'Non-stock purchase expense',
                $nonStockAmount,
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

        $this->createJournalEntry(
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
