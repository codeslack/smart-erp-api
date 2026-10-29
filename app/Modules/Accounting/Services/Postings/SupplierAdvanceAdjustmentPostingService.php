<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Modules\Purchase\Models\Purchase;
use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;

class SupplierAdvanceAdjustmentPostingService extends BasePostingService
{
    public function post(
        Purchase $purchase,
        float $adjustedAmount
    ): void {

        if ($adjustedAmount <= 0) {
            return;
        }

        $this->createJournalEntry(
            entryDate: $purchase->purchase_date,
            voucherType: JournalVoucherTypeEnum::SUPPLIER_ADVANCE_ADJUSTMENT->value,
            referenceType: Purchase::class,
            referenceId: $purchase->id,
            description: "Supplier Advance Adjustment {$purchase->purchase_no}",
            lines: [
                $this->debit(
                    AccountingAccounts::ACCOUNTS_PAYABLE,
                    $adjustedAmount
                ),
                $this->credit(
                    AccountingAccounts::SUPPLIER_ADVANCES,
                    $adjustedAmount
                ),
            ]
        );
    }
}
