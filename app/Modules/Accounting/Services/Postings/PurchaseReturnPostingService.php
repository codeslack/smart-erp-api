<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Core\Exceptions\BusinessException;

use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;
use App\Modules\Accounting\Enums\JournalEntryStatusEnum;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\JournalEntry\JournalEntryService;

use App\Modules\Inventory\Models\StockLedger;

use App\Modules\PurchaseReturn\Models\PurchaseReturn;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnRefundType;

use App\Modules\Settings\Models\Setting;

class PurchaseReturnPostingService extends BasePostingService
{
    public function __construct(
        JournalEntryService $journalEntryService
    ) {
        parent::__construct($journalEntryService);
    }

    public function post(
        PurchaseReturn $purchaseReturn
    ): JournalEntry {
        $purchaseReturn->loadMissing([
            'refundAccount',
            'purchase',
        ]);

        $purchase = $purchaseReturn->purchase;

        if (! $purchase || ! $purchase->exists) {
            throw new BusinessException(
                'The source purchase for this return could not be found.'
            );
        }

        $baseCurrency = strtoupper((string) (
            Setting::query()
                ->where('group', Setting::GROUP_COMPANY)
                ->where('key', 'currency')
                ->value('value') ?? 'INR'
        ));

        $currencyCode = strtoupper((string) (
            $purchaseReturn->currency_code
            ?: $purchase->currency_code
            ?: $baseCurrency
        ));

        $exchangeRate = $this->decimalRate(
            $purchaseReturn->exchange_rate
            ?? $purchase->exchange_rate
            ?? '1.00000000'
        );

        if (
            ! preg_match('/^[A-Z]{3}$/', $currencyCode)
            || $baseCurrency === ''
            || bccomp($exchangeRate, '0.00000000', 8) <= 0
        ) {
            throw new BusinessException(
                'Purchase return currency and exchange rate are invalid.'
            );
        }

        if (
            $currencyCode === $baseCurrency
            && bccomp($exchangeRate, '1.00000000', 8) !== 0
        ) {
            throw new BusinessException(
                'Purchase return exchange rate must be 1 when return currency matches company base currency.'
            );
        }

        $grandTotal = $this->decimal(
            $purchaseReturn->grand_total
        );

        $taxAmount = $this->decimal(
            $purchaseReturn->tax_amount ?? '0'
        );

        if (bccomp($taxAmount, $grandTotal, 4) > 0) {
            throw new BusinessException(
                'Purchase return tax amount cannot exceed the return total.'
            );
        }

        $baseGrandTotal = $this->decimal(
            bcmul($grandTotal, $exchangeRate, 8)
        );

        $baseTaxAmount = $this->decimal(
            bcmul($taxAmount, $exchangeRate, 8)
        );

        $baseNetTotal = bcsub(
            $baseGrandTotal,
            $baseTaxAmount,
            4
        );

        $inventoryCost = $this->decimal(
            StockLedger::query()
                ->where('referenceable_type', PurchaseReturn::class)
                ->where('referenceable_id', $purchaseReturn->id)
                ->where('quantity_out', '>', 0)
                ->sum('total_cost')
        );

        // Non-stock returns have no inventory ledger; preserve the existing
        // expense/inventory treatment until item-level purchase account mapping
        // is introduced for non-stock purchases.
        if (bccomp($inventoryCost, '0.0000', 4) === 0) {
            $inventoryCost = $baseNetTotal;
        }

        $variance = bcsub(
            $baseNetTotal,
            $inventoryCost,
            4
        );

        $foreignInventoryCost = $this->decimal(
            bcdiv($inventoryCost, $exchangeRate, 8)
        );

        $foreignVariance = $this->decimal(
            bcdiv(
                ltrim($variance, '-'),
                $exchangeRate,
                8
            )
        );

        $refundType = $purchaseReturn->refund_type;

        if (is_string($refundType)) {
            $refundType = PurchaseReturnRefundType::from($refundType);
        }

        if ($refundType === PurchaseReturnRefundType::CREDIT_NOTE) {
            $counterLines = [
                $this->debit(
                    AccountingAccounts::ACCOUNTS_PAYABLE,
                    $baseGrandTotal,
                    'Purchase return credit note',
                    $grandTotal,
                ),
            ];
        } else {
            $refundAccount = $purchaseReturn->refundAccount;

            if (! $refundAccount || ! $refundAccount->is_active) {
                throw new BusinessException(
                    'A valid active refund account is required for a supplier refund.'
                );
            }

            $counterLines = [
                $this->debit(
                    $refundAccount->id,
                    $baseGrandTotal,
                    'Purchase return refund',
                    $grandTotal,
                ),
            ];
        }

        $lines = array_merge(
            $counterLines,
            [
                $this->credit(
                    AccountingAccounts::INVENTORY,
                    $inventoryCost,
                    'Inventory returned to supplier',
                    $foreignInventoryCost,
                ),
            ]
        );

        if (bccomp($baseTaxAmount, '0.0000', 4) > 0) {
            $lines[] = $this->credit(
                AccountingAccounts::INPUT_TAX,
                $baseTaxAmount,
                'Purchase return input tax reversal',
                $taxAmount,
            );
        }

        if (bccomp($variance, '0.0000', 4) > 0) {
            $lines[] = $this->credit(
                AccountingAccounts::PURCHASE_RETURN_VARIANCE,
                $variance,
                'Purchase return valuation gain',
                $foreignVariance,
            );
        } elseif (bccomp($variance, '0.0000', 4) < 0) {
            $lines[] = $this->debit(
                AccountingAccounts::PURCHASE_RETURN_VARIANCE,
                ltrim($variance, '-'),
                'Purchase return valuation loss',
                $foreignVariance,
            );
        }

        $entry = $this->journalEntryService->createAndPost([
            'tenant_id' => tenantId(),
            'reference_type' => PurchaseReturn::class,
            'reference_id' => $purchaseReturn->id,
            'voucher_type' => JournalVoucherTypeEnum::PURCHASE_RETURN,
            'entry_date' => $purchaseReturn->return_date,
            'description' => 'Purchase Return ' . $purchaseReturn->return_no,
            'currency_code' => $currencyCode,
            'exchange_rate' => $exchangeRate,
            'status' => JournalEntryStatusEnum::DRAFT,
            'created_by' => userId(),
            'lines' => $lines,
        ]);

        $purchaseReturn->update([
            'journal_entry_id' => $entry->id,
        ]);

        return $entry;
    }
}
