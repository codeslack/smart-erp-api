<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Modules\Sales\Models\Sale;
use App\Core\Exceptions\BusinessException;
use App\Modules\Inventory\Models\StockLedger;
use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;
use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;
use App\Modules\Inventory\Enums\StockTransactionType;

class SalesPostingService extends BasePostingService
{
    public function post(
        Sale $sale
    ): void {
        $grandTotal = $this->decimal($sale->grand_total);
        $this->validateAmount($grandTotal);

        $currencyCode = strtoupper((string) $sale->currency_code);
        $exchangeRate = (string) $sale->exchange_rate;

        if (! preg_match('/^[A-Z]{3}$/', $currencyCode)) {
            throw new \\App\\Core\\Exceptions\\BusinessException(
                'Sale currency code must be a valid three-letter code.'
            );
        }

        if (! is_numeric($exchangeRate) || bccomp($exchangeRate, '0', 8) <= 0) {
            throw new \\App\\Core\\Exceptions\\BusinessException(
                'Sale exchange rate must be greater than zero.'
            );
        }

        // Exchange rate convention: base-currency units per one transaction-currency unit.
        $baseGrandTotal = bcadd(
            bcmul($grandTotal, $exchangeRate, 8),
            '0',
            4
        );

        $this->validateAmount($baseGrandTotal);

        $inventoryCost = StockLedger::query()
            ->where('tenant_id', tenantId())
            ->where('referenceable_type', Sale::class)
            ->where('referenceable_id', $sale->id)
            ->whereIn(
                'transaction_type',
                [StockTransactionType::SALE, InventoryTransactionTypeEnum::SALE->value]
            )
            ->sum('total_cost');

        $entryDate = $sale->sale_date instanceof \DateTimeInterface
            ? $sale->sale_date->format('Y-m-d')
            : (string) $sale->sale_date;

        /*
        |--------------------------------------------------------------------------
        | Revenue Entry
        |--------------------------------------------------------------------------
        |
        | Dr Accounts Receivable
        | Cr Sales Revenue
        |
        */
        $this->createJournalEntry(
            entryDate: $entryDate,
            voucherType: JournalVoucherTypeEnum::SALE->value,
            referenceType: Sale::class,
            referenceId: $sale->id,
            description: "Sale {$sale->sale_no}",
            lines: [
                $this->debit(
                    AccountingAccounts::ACCOUNTS_RECEIVABLE,
                    $baseGrandTotal,
                    foreignAmount: $grandTotal
                ),
                $this->credit(
                    AccountingAccounts::SALES_REVENUE,
                    $baseGrandTotal,
                    foreignAmount: $grandTotal
                ),
            ],
            currencyCode: $currencyCode,
            exchangeRate: $exchangeRate
        );

        /*
        |--------------------------------------------------------------------------
        | Cost Of Goods Sold Entry
        |--------------------------------------------------------------------------
        |
        | Dr Cost Of Goods Sold
        | Cr Inventory
        |
        */
        if ($inventoryCost > 0) {
            $costAmount = $this->decimal($inventoryCost);

            $this->createJournalEntry(
                entryDate: $entryDate,
                voucherType: JournalVoucherTypeEnum::SALE->value,
                referenceType: Sale::class,
                referenceId: $sale->id,
                description: "COGS {$sale->sale_no}",
                lines: [
                    $this->debit(
                        AccountingAccounts::COST_OF_GOODS_SOLD,
                        $costAmount
                    ),
                    $this->credit(
                        AccountingAccounts::INVENTORY,
                        $costAmount
                    ),
                ]
            );
        }
    }
}
