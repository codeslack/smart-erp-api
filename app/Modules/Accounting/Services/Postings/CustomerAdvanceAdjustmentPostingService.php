<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Modules\Sales\Models\Sale;
use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;

class CustomerAdvanceAdjustmentPostingService extends BasePostingService
{
    public function post(
        Sale $sale,
        float $adjustedAmount
    ): void {

        if ($adjustedAmount <= 0) {
            return;
        }

        $this->createJournalEntry(
            entryDate: $sale->sale_date,
            voucherType: JournalVoucherTypeEnum::CUSTOMER_ADVANCE_ADJUSTMENT->value,
            referenceType: Sale::class,
            referenceId: $sale->id,
            description: "Customer Advance Adjustment {$sale->sale_no}",
            lines: [
                $this->debit(
                    AccountingAccounts::CUSTOMER_ADVANCES,
                    $adjustedAmount
                ),
                $this->credit(
                    AccountingAccounts::ACCOUNTS_RECEIVABLE,
                    $adjustedAmount
                ),
            ]
        );
    }
}
