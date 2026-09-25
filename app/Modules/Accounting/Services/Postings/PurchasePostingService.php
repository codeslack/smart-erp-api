<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Modules\Purchase\Models\Purchase;

use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;

class PurchasePostingService extends BasePostingService
{
    public function post(
        Purchase $purchase
    ): void {
        $amount = $this->decimal(
            $purchase->grand_total
        );

        $this->validateAmount(
            $amount
        );

        $this->createJournalEntry(
            entryDate:
                $purchase->purchase_date,

            voucherType:
                JournalVoucherTypeEnum::PURCHASE->value,

            referenceType:
                Purchase::class,

            referenceId:
                $purchase->id,

            description:
                "Purchase {$purchase->purchase_no}",

            lines: [
                $this->debit(
                    AccountingAccounts::INVENTORY,
                    $amount
                ),

                $this->credit(
                    AccountingAccounts::ACCOUNTS_PAYABLE,
                    $amount
                ),
            ]
        );
    }
}