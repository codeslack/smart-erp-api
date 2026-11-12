<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Core\Exceptions\BusinessException;
use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalEntryStatusEnum;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnRefundType;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;
use App\Modules\Settings\Models\Setting;

class PurchaseReturnPostingService extends BasePostingService
{
    public function post(PurchaseReturn $purchaseReturn): void
    {
        $grandTotal = $this->decimal($purchaseReturn->grand_total);
        $this->validateAmount($grandTotal);

        $purchaseReturn->loadMissing(['purchase', 'refundAccount']);

        $purchase = $purchaseReturn->purchase;
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
            (string) ($purchase?->currency_code ?: $baseCurrency)
        );

        if (! preg_match('/^[A-Z]{3}$/', $currencyCode)) {
            throw new BusinessException(
                'Purchase currency code must be a valid three-letter code.'
            );
        }

        $exchangeRate = $this->decimalRate(
            $purchase?->exchange_rate ?? '1.00000000'
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

        $taxForeign = $this->decimal(
            $purchaseReturn->tax_amount ?? '0'
        );

        $taxBase = $this->decimal(
            bcmul($taxForeign, $exchangeRate, 8)
        );

        $inventoryForeign = bcsub(
            $grandTotal,
            $taxForeign,
            4
        );

        if (bccomp($inventoryForeign, '0.0000', 4) < 0) {
            throw new BusinessException(
                'Purchase return tax cannot exceed the return total.'
            );
        }

        $inventoryBase = $this->decimal(
            bcmul($inventoryForeign, $exchangeRate, 8)
        );

        $lines = [];

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
                $grandTotal
            );
        }

        if (bccomp($inventoryForeign, '0.0000', 4) > 0) {
            $lines[] = $this->credit(
                AccountingAccounts::INVENTORY,
                $inventoryBase,
                "Purchase Return Inventory {$purchaseReturn->return_no}",
                $inventoryForeign
            );
        }

        if (bccomp($taxForeign, '0.0000', 4) > 0) {
            $lines[] = $this->credit(
                AccountingAccounts::INPUT_TAX_RECEIVABLE,
                $taxBase,
                "Purchase Return Tax {$purchaseReturn->return_no}",
                $taxForeign
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
