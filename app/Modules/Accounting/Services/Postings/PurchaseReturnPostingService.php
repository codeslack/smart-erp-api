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
            'items.product',
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

        $stockNetTotal = '0.0000';
        $nonStockNetTotal = '0.0000';
        $hasStockItems = false;

        foreach ($purchaseReturn->items as $item) {
            $lineNet = bcsub(
                $this->decimal($item->subtotal),
                $this->decimal($item->discount_amount ?? '0'),
                4
            );

            if ($item->product?->requiresStock()) {
                $hasStockItems = true;
                $stockNetTotal = bcadd($stockNetTotal, $lineNet, 4);
            } else {
                $nonStockNetTotal = bcadd($nonStockNetTotal, $lineNet, 4);
            }
        }

        $roundingAmount = $this->decimal(
            $purchaseReturn->rounding_amount ?? '0'
        );

        if ($hasStockItems) {
            $stockNetTotal = bcadd($stockNetTotal, $roundingAmount, 4);
        } else {
            $nonStockNetTotal = bcadd($nonStockNetTotal, $roundingAmount, 4);
        }

        $calculatedNetTotal = bcadd(
            $stockNetTotal,
            $nonStockNetTotal,
            4
        );

        $expectedNetTotal = bcsub(
            $grandTotal,
            $taxAmount,
            4
        );

        $roundingDifference = bcsub(
            $expectedNetTotal,
            $calculatedNetTotal,
            4
        );

        if (bccomp($roundingDifference, '0.0000', 4) !== 0) {
            if ($hasStockItems) {
                $stockNetTotal = bcadd($stockNetTotal, $roundingDifference, 4);
            } else {
                $nonStockNetTotal = bcadd($nonStockNetTotal, $roundingDifference, 4);
            }
        }

        $baseStockNetTotal = $this->decimal(
            bcmul($stockNetTotal, $exchangeRate, 8)
        );

        $baseNonStockNetTotal = $this->decimal(
            bcmul($nonStockNetTotal, $exchangeRate, 8)
        );

        $inventoryCost = $this->decimal(
            StockLedger::query()
                ->where('referenceable_type', PurchaseReturn::class)
                ->where('referenceable_id', $purchaseReturn->id)
                ->where('quantity_out', '>', 0)
                ->sum('total_cost')
        );

        if (
            $hasStockItems
            && bccomp($inventoryCost, '0.0000', 4) === 0
        ) {
            $inventoryCost = $baseStockNetTotal;
        }

        $variance = bcsub(
            $baseStockNetTotal,
            $inventoryCost,
            4
        );

        $foreignInventoryCost = $this->decimal(
            bcdiv($inventoryCost, $exchangeRate, 8)
        );

        // Allocate the document-currency residual so the inventory and
        // variance lines reconcile exactly after four-decimal rounding.
        $foreignVariance = $this->decimal(
            ltrim(
                bcsub(
                    $stockNetTotal,
                    $foreignInventoryCost,
                    4
                ),
                '-'
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
                    $refundAccount->account_code,
                    $baseGrandTotal,
                    'Purchase return refund',
                    $grandTotal,
                ),
            ];
        }

        $lines = array_merge(
            $counterLines,
            [
                ...(
                    bccomp($inventoryCost, '0.0000', 4) > 0
                        ? [
                            $this->credit(
                                AccountingAccounts::INVENTORY,
                                $inventoryCost,
                                'Inventory returned to supplier',
                                $foreignInventoryCost,
                            ),
                        ]
                        : []
                ),
                ...(
                    bccomp($baseNonStockNetTotal, '0.0000', 4) > 0
                        ? [
                            $this->credit(
                                AccountingAccounts::PURCHASE_EXPENSE,
                                $baseNonStockNetTotal,
                                'Non-stock purchase return',
                                $nonStockNetTotal,
                            ),
                        ]
                        : []
                ),
            ]
        );

        if (bccomp($baseTaxAmount, '0.0000', 4) > 0) {
            $lines[] = $this->credit(
                AccountingAccounts::INPUT_TAX_RECEIVABLE,
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
