<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Modules\Purchase\Models\Purchase;
use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;

class PurchasePostingService extends BasePostingService
{
    public function post(Purchase $purchase): void
    {
        $grandTotal = $this->decimal($purchase->grand_total);
        $this->validateAmount($grandTotal);

        $purchase->loadMissing(['charges.chartOfAccount']);
        $lines = [];
        $expenseChargesTotal = 0.0;

        foreach ($purchase->charges as $charge) {
            $chargeAmount = (float) $charge->amount;
            if ($chargeAmount <= 0) {
                continue;
            }

            if ($charge->treatment === 'expense' && $charge->chartOfAccount) {
                $expenseChargesTotal += $chargeAmount;
                $lines[] = [
                    'chart_of_account_uuid' => $charge->chartOfAccount->uuid,
                    'debit' => $this->decimal($chargeAmount),
                    'credit' => '0',
                    'description' => "Charge: {$charge->name}",
                ];
            }
        }

        $taxAmount = (float) $purchase->tax_amount;
        $inventoryAmount = max(0, (float) $purchase->grand_total - $taxAmount - $expenseChargesTotal);

        if ($inventoryAmount > 0) {
            $lines[] = $this->debit(
                AccountingAccounts::INVENTORY,
                $this->decimal($inventoryAmount)
            );
        }

        if ($taxAmount > 0) {
            $lines[] = $this->debit(
                AccountingAccounts::INPUT_TAX_RECEIVABLE,
                $this->decimal($taxAmount)
            );
        }

        $lines[] = $this->credit(
            AccountingAccounts::ACCOUNTS_PAYABLE,
            $grandTotal
        );

        $this->createJournalEntry(
            entryDate: $purchase->purchase_date instanceof \DateTimeInterface
                ? $purchase->purchase_date->format('Y-m-d')
                : (string) $purchase->purchase_date,
            voucherType: JournalVoucherTypeEnum::PURCHASE->value,
            referenceType: Purchase::class,
            referenceId: $purchase->id,
            description: "Purchase {$purchase->purchase_no}",
            lines: $lines
        );
    }
}
