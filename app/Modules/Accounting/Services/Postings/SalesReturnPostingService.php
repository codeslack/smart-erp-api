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
use App\Modules\SalesReturn\Enums\SalesReturnRefundType;
use App\Modules\SalesReturn\Models\SalesReturn;

class SalesReturnPostingService extends BasePostingService
{
    public function __construct(JournalEntryService $journalEntryService)
    {
        parent::__construct($journalEntryService);
    }

    public function post(SalesReturn $salesReturn): void
    {
        $grandTotal = $this->decimal($salesReturn->grand_total);
        $this->validateAmount($grandTotal);
        $salesReturn->loadMissing('refundAccount', 'items');

        $exchangeRate = $this->decimalRate($salesReturn->exchange_rate);
        $currencyCode = strtoupper((string) $salesReturn->currency_code);
        $baseGrandTotal = $this->decimal(bcmul($grandTotal, $exchangeRate, 8));

        $foreignTaxTotal = '0.0000';
        $baseTaxTotal = '0.0000';
        $taxLines = [];

        foreach ($salesReturn->items as $item) {
            $snapshot = $item->tax_snapshot;
            if (! is_array($snapshot)) {
                continue;
            }

            foreach (($snapshot['components'] ?? []) as $component) {
                $foreignAmount = $this->decimal($component['tax_amount'] ?? '0');
                if (bccomp($foreignAmount, '0.0000', 4) <= 0) {
                    continue;
                }

                $accountUuid = $component['tax_account_uuid'] ?? null;
                if (! $accountUuid) {
                    throw new BusinessException(
                        'The source Sale tax snapshot does not contain its original tax account mapping.'
                    );
                }

                $account = ChartOfAccount::query()
                    ->where('tenant_id', tenantId())
                    ->where('uuid', $accountUuid)
                    ->where('is_active', true)
                    ->first();

                if (! $account) {
                    throw new BusinessException('The original output tax account is missing or inactive.');
                }

                $baseAmount = $this->decimal(bcmul($foreignAmount, $exchangeRate, 8));
                $taxLines[] = [
                    'chart_of_account_uuid' => $account->uuid,
                    'debit' => $baseAmount,
                    'credit' => '0.0000',
                    'foreign_debit' => $foreignAmount,
                    'foreign_credit' => null,
                    'description' => 'Output tax reversal component ' . ($component['tax_component_id'] ?? ''),
                ];

                $foreignTaxTotal = bcadd($foreignTaxTotal, $foreignAmount, 4);
                $baseTaxTotal = bcadd($baseTaxTotal, $baseAmount, 4);
            }
        }

        if (bccomp($foreignTaxTotal, $this->decimal($salesReturn->tax_amount), 4) !== 0
            && bccomp($this->decimal($salesReturn->tax_amount), '0.0000', 4) > 0) {
            throw new BusinessException('Sales Return tax snapshot totals do not match the return tax amount.');
        }

        $netRevenue = bcsub($grandTotal, $foreignTaxTotal, 4);
        $baseNetRevenue = bcsub($baseGrandTotal, $baseTaxTotal, 4);
        if (bccomp($baseNetRevenue, '0.0000', 4) < 0) {
            throw new BusinessException('Sales Return tax exceeds the return total.');
        }

        $inventoryCost = StockLedger::query()
            ->where('tenant_id', tenantId())
            ->where('referenceable_type', SalesReturn::class)
            ->where('referenceable_id', $salesReturn->id)
            ->whereIn('transaction_type', [
                StockTransactionType::SALES_RETURN,
                InventoryTransactionTypeEnum::SALE_RETURN->value,
            ])
            ->sum('total_cost');

        $entryDate = $salesReturn->return_date instanceof \DateTimeInterface
            ? $salesReturn->return_date->format('Y-m-d')
            : (string) $salesReturn->return_date;

        $refundType = $salesReturn->refund_type ?? SalesReturnRefundType::CREDIT_NOTE;
        if (is_string($refundType)) {
            $refundType = SalesReturnRefundType::tryFrom($refundType)
                ?? SalesReturnRefundType::CREDIT_NOTE;
        }

        $counterLines = [];
        if ($refundType === SalesReturnRefundType::CREDIT_NOTE) {
            $counterLines[] = $this->credit(
                AccountingAccounts::ACCOUNTS_RECEIVABLE,
                $baseGrandTotal,
                "Sales Return Credit Note {$salesReturn->return_no}",
                $grandTotal,
            );
        } else {
            if (! $salesReturn->refundAccount?->is_active) {
                throw new BusinessException('The selected Sales Return refund account is inactive.');
            }

            $refundAmount = $this->decimal($salesReturn->refund_amount);
            $creditedAmount = $this->decimal($salesReturn->credited_amount);

            if (bccomp(bcadd($refundAmount, $creditedAmount, 4), $grandTotal, 4) !== 0) {
                throw new BusinessException('Sales Return refund and receivable credit amounts do not equal the return total.');
            }

            if (bccomp($refundAmount, '0.0000', 4) > 0) {
                $counterLines[] = [
                    'chart_of_account_uuid' => $salesReturn->refundAccount->uuid,
                    'debit' => '0.0000',
                    'credit' => $this->decimal(bcmul($refundAmount, $exchangeRate, 8)),
                    'foreign_debit' => null,
                    'foreign_credit' => $refundAmount,
                    'description' => "Sales Return Refund {$salesReturn->return_no}",
                ];
            }

            if (bccomp($creditedAmount, '0.0000', 4) > 0) {
                $counterLines[] = $this->credit(
                    AccountingAccounts::ACCOUNTS_RECEIVABLE,
                    $this->decimal(bcmul($creditedAmount, $exchangeRate, 8)),
                    "Sales Return Receivable Credit {$salesReturn->return_no}",
                    $creditedAmount,
                );
            }
        }

        $this->createJournalEntry(
            entryDate: $entryDate,
            voucherType: JournalVoucherTypeEnum::SALES_RETURN->value,
            referenceType: SalesReturn::class,
            referenceId: $salesReturn->id,
            description: "Sales Return {$salesReturn->return_no}",
            lines: array_merge([
                $this->debit(
                    AccountingAccounts::SALES_RETURN,
                    $baseNetRevenue,
                    foreignAmount: $netRevenue,
                ),
            ], $taxLines, $counterLines),
            currencyCode: $currencyCode,
            exchangeRate: $exchangeRate,
        );

        if ($inventoryCost > 0) {
            $costAmount = $this->decimal($inventoryCost);
            $this->createJournalEntry(
                entryDate: $entryDate,
                voucherType: JournalVoucherTypeEnum::SALES_RETURN->value,
                referenceType: SalesReturn::class,
                referenceId: $salesReturn->id,
                description: "Inventory Restore {$salesReturn->return_no}",
                lines: [
                    $this->debit(AccountingAccounts::INVENTORY, $costAmount),
                    $this->credit(AccountingAccounts::COST_OF_GOODS_SOLD, $costAmount),
                ],
            );
        }
    }
}
