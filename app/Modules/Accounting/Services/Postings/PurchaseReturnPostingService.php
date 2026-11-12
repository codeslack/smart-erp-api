<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Core\Exceptions\BusinessException;
use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalEntryStatusEnum;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnRefundType;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;

class PurchaseReturnPostingService extends BasePostingService
{
    public function post(PurchaseReturn $purchaseReturn): void
    {
        $grandTotal = $this->decimal($purchaseReturn->grand_total);
        $this->validateAmount($grandTotal);

        $currency = $this->resolveCurrencyContext(
            $purchaseReturn->currency_code,
            $purchaseReturn->exchange_rate,
            'Purchase Return',
        );
        $currencyCode = $currency['currency_code'];
        $exchangeRate = $currency['exchange_rate'];

        $baseGrandTotal = $this->decimal(
            bcmul($grandTotal, $exchangeRate, 8)
        );

        $purchaseReturn->loadMissing(['refundAccount', 'purchase']);

        $taxAmount = $this->decimal($purchaseReturn->tax_amount);
        $baseTaxAmount = $this->decimal(
            bcmul($taxAmount, $exchangeRate, 8)
        );

        $inventoryAmount = bcsub($grandTotal, $taxAmount, 4);
        $baseInventoryAmount = bcsub(
            $baseGrandTotal,
            $baseTaxAmount,
            4,
        );

        if (
            bccomp($inventoryAmount, '0.0000', 4) < 0
            || bccomp($baseInventoryAmount, '0.0000', 4) < 0
        ) {
            throw new BusinessException(
                'Purchase return tax cannot exceed the return total.'
            );
        }

        $lines = [];

        // Debit: Accounts Payable for credit notes or the selected refund account.
        if (
            $purchaseReturn->refund_type !== PurchaseReturnRefundType::CREDIT_NOTE
            && $purchaseReturn->refundAccount
        ) {
            $lines[] = [
                'chart_of_account_uuid' => $purchaseReturn->refundAccount->uuid,
                'debit' => $baseGrandTotal,
                'credit' => '0.0000',
                'foreign_debit' => $grandTotal,
                'foreign_credit' => null,
                'description' => "Purchase Return Refund {$purchaseReturn->return_no}",
            ];
        } else {
            $lines[] = $this->debit(
                AccountingAccounts::ACCOUNTS_PAYABLE,
                $baseGrandTotal,
                "Purchase Return {$purchaseReturn->return_no}",
                $grandTotal,
            );
        }

        if (bccomp($inventoryAmount, '0.0000', 4) > 0) {
            $lines[] = $this->credit(
                AccountingAccounts::INVENTORY,
                $baseInventoryAmount,
                "Purchase Return Inventory {$purchaseReturn->return_no}",
                $inventoryAmount,
            );
        }

        if (bccomp($taxAmount, '0.0000', 4) > 0) {
            $lines[] = $this->credit(
                AccountingAccounts::INPUT_TAX_RECEIVABLE,
                $baseTaxAmount,
                "Purchase Return Tax {$purchaseReturn->return_no}",
                $taxAmount,
            );
        }

        $entryDate = $purchaseReturn->return_date instanceof \DateTimeInterface
            ? $purchaseReturn->return_date->format('Y-m-d')
            : (string) $purchaseReturn->return_date;

        $entry = $this->journalEntryService->createAndPost([
            'voucher_type' => JournalVoucherTypeEnum::PURCHASE_RETURN->value,
            'reference_type' => PurchaseReturn::class,
            'reference_id' => $purchaseReturn->id,
            'entry_date' => $entryDate,
            'currency_code' => $currencyCode,
            'exchange_rate' => $exchangeRate,
            'description' => "Purchase Return {$purchaseReturn->return_no}",
            'status' => JournalEntryStatusEnum::DRAFT,
            'created_by' => auth()->id() ?? userId(),
            'lines' => $lines,
        ]);

        if ($entry) {
            $purchaseReturn->update([
                'journal_entry_id' => $entry->id,
            ]);
        }
    }
}
