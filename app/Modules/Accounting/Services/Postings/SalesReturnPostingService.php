<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Modules\Inventory\Models\StockLedger;
use App\Modules\SalesReturn\Models\SalesReturn;
use App\Modules\SalesReturn\Enums\SalesReturnRefundType;
use App\Core\Exceptions\BusinessException;
use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;
use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;
use App\Modules\Inventory\Enums\StockTransactionType;

class SalesReturnPostingService extends BasePostingService
{
    public function post(
        SalesReturn $salesReturn
    ): void {
        $grandTotal = $this->decimal($salesReturn->grand_total);
        $this->validateAmount($grandTotal);
        $salesReturn->loadMissing('refundAccount');

        $inventoryCost = StockLedger::query()
            ->where('tenant_id', tenantId())
            ->where('referenceable_type', SalesReturn::class)
            ->where('referenceable_id', $salesReturn->id)
            ->whereIn(
                'transaction_type',
                [StockTransactionType::SALES_RETURN, InventoryTransactionTypeEnum::SALE_RETURN->value]
            )
            ->sum('total_cost');

        $entryDate = $salesReturn->return_date instanceof \DateTimeInterface
            ? $salesReturn->return_date->format('Y-m-d')
            : (string) $salesReturn->return_date;

        /*
        |--------------------------------------------------------------------------
        | Reverse Revenue
        |--------------------------------------------------------------------------
        |
        | Dr Sales Return
        | Cr Accounts Receivable
        |
        */
        $refundType = $salesReturn->refund_type
            ?? SalesReturnRefundType::CREDIT_NOTE;

        if (is_string($refundType)) {
            $refundType = SalesReturnRefundType::tryFrom($refundType)
                ?? SalesReturnRefundType::CREDIT_NOTE;
        }

        $counterLine = $refundType === SalesReturnRefundType::CREDIT_NOTE
            ? $this->credit(
                AccountingAccounts::ACCOUNTS_RECEIVABLE,
                $grandTotal,
                "Sales Return Credit Note {$salesReturn->return_no}"
            )
            : [
                'chart_of_account_uuid' => $salesReturn->refundAccount?->uuid
                    ?? throw new BusinessException('A valid refund account is required for this Sales Return.'),
                'debit' => '0.0000',
                'credit' => $grandTotal,
                'description' => "Sales Return Refund {$salesReturn->return_no}",
            ];

        if (
            $refundType !== SalesReturnRefundType::CREDIT_NOTE
            && ! $salesReturn->refundAccount?->is_active
        ) {
            throw new BusinessException('The selected Sales Return refund account is inactive.');
        }

        $this->createJournalEntry(
            entryDate: $entryDate,
            voucherType: JournalVoucherTypeEnum::SALES_RETURN->value,
            referenceType: SalesReturn::class,
            referenceId: $salesReturn->id,
            description: "Sales Return {$salesReturn->return_no}",
            lines: [
                $this->debit(
                    AccountingAccounts::SALES_RETURN,
                    $grandTotal
                ),
                $counterLine,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Restore Inventory
        |--------------------------------------------------------------------------
        |
        | Dr Inventory
        | Cr Cost Of Goods Sold
        |
        */
        if ($inventoryCost > 0) {
            $costAmount = $this->decimal($inventoryCost);

            $this->createJournalEntry(
                entryDate: $entryDate,
                voucherType: JournalVoucherTypeEnum::SALES_RETURN->value,
                referenceType: SalesReturn::class,
                referenceId: $salesReturn->id,
                description: "Inventory Restore {$salesReturn->return_no}",
                lines: [
                    $this->debit(
                        AccountingAccounts::INVENTORY,
                        $costAmount
                    ),
                    $this->credit(
                        AccountingAccounts::COST_OF_GOODS_SOLD,
                        $costAmount
                    ),
                ]
            );
        }
    }
}
