<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Modules\Inventory\Models\StockLedger;
use App\Modules\SalesReturn\Models\SalesReturn;
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
                $this->credit(
                    AccountingAccounts::ACCOUNTS_RECEIVABLE,
                    $grandTotal
                ),
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
