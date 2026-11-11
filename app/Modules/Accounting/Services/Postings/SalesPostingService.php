<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Core\Exceptions\BusinessException;
use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Services\JournalEntry\JournalEntryService;
use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;
use App\Modules\Inventory\Enums\StockTransactionType;
use App\Modules\Inventory\Models\StockLedger;
use App\Modules\Sales\Models\Sale;
use App\Modules\Settings\Models\Setting;
use App\Modules\Tax\Domain\TaxCalculationResult;
use App\Modules\Tax\Enums\TaxDirectionEnum;
use App\Modules\Tax\Services\TaxPostingService;

class SalesPostingService extends BasePostingService
{
    public function __construct(
        JournalEntryService $journalEntryService,
        protected TaxPostingService $taxPostingService,
    ) {
        parent::__construct($journalEntryService);
    }

    public function post(Sale $sale): void
    {
        $grandTotal = $this->decimal($sale->grand_total);
        $this->validateAmount($grandTotal);

        $baseCurrency = (string) (Setting::query()
            ->where('group', Setting::GROUP_COMPANY)
            ->where('key', 'currency')
            ->value('value') ?? 'INR');
        $currencyCode = strtoupper((string) ($sale->currency_code ?: $baseCurrency));
        $exchangeRate = $this->decimalRate($sale->exchange_rate ?? '1.00000000');

        if (! preg_match('/^[A-Z]{3}$/', $currencyCode)) {
            throw new BusinessException('Sale currency code must be a valid three-letter code.');
        }

        if ($baseCurrency === '') {
            throw new BusinessException('Company base currency is not configured.');
        }

        if ($currencyCode === strtoupper($baseCurrency) && bccomp($exchangeRate, '1.00000000', 8) !== 0) {
            throw new BusinessException('Exchange rate must be 1 when Sale currency matches company base currency.');
        }

        $baseGrandTotal = $this->decimal(bcmul($grandTotal, $exchangeRate, 8));

        $sale->loadMissing('items');
        $taxLines = [];
        $foreignTaxTotal = '0.0000';
        $baseTaxTotal = '0.0000';

        foreach ($sale->items as $item) {
            $snapshot = $item->tax_snapshot;

            if (! is_array($snapshot) || empty($snapshot['components'])) {
                continue;
            }

            $calculation = new TaxCalculationResult(
                taxableAmount: (float) ($snapshot['taxable_amount'] ?? 0),
                totalTax: (float) ($snapshot['tax_amount'] ?? 0),
                totalAmount: (float) ($snapshot['taxable_amount'] ?? 0) + (float) ($snapshot['tax_amount'] ?? 0),
                components: $snapshot['components'],
            );

            foreach ($this->taxPostingService->buildLines(
                $calculation,
                TaxDirectionEnum::OUTPUT,
                (string) $snapshot['calculation_date'],
            ) as $postingLine) {
                $account = ChartOfAccount::query()
                    ->where('tenant_id', tenantId())
                    ->whereKey($postingLine->accountId)
                    ->where('is_active', true)
                    ->first();

                if (! $account) {
                    throw new BusinessException('The mapped output tax account is missing or inactive.');
                }

                $foreignAmount = $this->decimal($postingLine->amount);
                $baseAmount = $this->decimal(bcmul($foreignAmount, $exchangeRate, 8));

                $taxLines[] = [
                    'chart_of_account_uuid' => $account->uuid,
                    'debit' => '0.0000',
                    'credit' => $baseAmount,
                    'foreign_debit' => null,
                    'foreign_credit' => $foreignAmount,
                    'description' => 'Output tax component ' . $postingLine->taxComponentId,
                ];

                foreach ($snapshot['components'] as $componentIndex => $componentSnapshot) {
                    if ((int) ($componentSnapshot['tax_component_id'] ?? 0) === $postingLine->taxComponentId) {
                        $snapshot['components'][$componentIndex]['tax_account_uuid'] = $account->uuid;
                    }
                }

                $foreignTaxTotal = bcadd($foreignTaxTotal, $foreignAmount, 4);
                $baseTaxTotal = bcadd($baseTaxTotal, $baseAmount, 4);
            }

            $item->update(['tax_snapshot' => $snapshot]);
        }

        $aggregatedTaxLines = [];
        foreach ($taxLines as $line) {
            $accountUuid = $line['chart_of_account_uuid'];
            if (! isset($aggregatedTaxLines[$accountUuid])) {
                $aggregatedTaxLines[$accountUuid] = $line;
                continue;
            }

            $aggregatedTaxLines[$accountUuid]['credit'] = bcadd(
                $aggregatedTaxLines[$accountUuid]['credit'],
                $line['credit'],
                4,
            );
            $aggregatedTaxLines[$accountUuid]['foreign_credit'] = bcadd(
                $aggregatedTaxLines[$accountUuid]['foreign_credit'],
                $line['foreign_credit'],
                4,
            );
        }
        $taxLines = array_values($aggregatedTaxLines);

        if (bccomp($foreignTaxTotal, $this->decimal($sale->tax_amount), 4) !== 0) {
            if (bccomp($this->decimal($sale->tax_amount), '0.0000', 4) > 0) {
                throw new BusinessException('Sale tax snapshot totals do not match the Sale tax amount.');
            }
        }

        $foreignRevenue = bcsub($grandTotal, $foreignTaxTotal, 4);
        $baseRevenue = bcsub($baseGrandTotal, $baseTaxTotal, 4);

        if (bccomp($baseRevenue, '0.0000', 4) < 0) {
            throw new BusinessException('Sale tax exceeds the Sale total after currency conversion.');
        }

        $entryDate = $sale->sale_date instanceof \DateTimeInterface
            ? $sale->sale_date->format('Y-m-d')
            : (string) $sale->sale_date;

        $this->createJournalEntry(
            entryDate: $entryDate,
            voucherType: JournalVoucherTypeEnum::SALE->value,
            referenceType: Sale::class,
            referenceId: $sale->id,
            description: "Sale {$sale->sale_no}",
            lines: array_merge([
                $this->debit(
                    AccountingAccounts::ACCOUNTS_RECEIVABLE,
                    $baseGrandTotal,
                    foreignAmount: $grandTotal,
                ),
                $this->credit(
                    AccountingAccounts::SALES_REVENUE,
                    $baseRevenue,
                    foreignAmount: $foreignRevenue,
                ),
            ], $taxLines),
            currencyCode: $currencyCode,
            exchangeRate: $exchangeRate,
        );

        $inventoryCost = StockLedger::query()
            ->where('tenant_id', tenantId())
            ->where('referenceable_type', Sale::class)
            ->where('referenceable_id', $sale->id)
            ->whereIn('transaction_type', [
                StockTransactionType::SALE,
                InventoryTransactionTypeEnum::SALE->value,
            ])
            ->sum('total_cost');

        if ($inventoryCost > 0) {
            $costAmount = $this->decimal($inventoryCost);

            $this->createJournalEntry(
                entryDate: $entryDate,
                voucherType: JournalVoucherTypeEnum::SALE->value,
                referenceType: Sale::class,
                referenceId: $sale->id,
                description: "COGS {$sale->sale_no}",
                lines: [
                    $this->debit(AccountingAccounts::COST_OF_GOODS_SOLD, $costAmount),
                    $this->credit(AccountingAccounts::INVENTORY, $costAmount),
                ],
            );
        }
    }
}
