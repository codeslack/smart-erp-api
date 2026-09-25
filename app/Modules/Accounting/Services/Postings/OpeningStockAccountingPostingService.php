<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;
use App\Modules\OpeningStock\Models\OpeningStock;

class OpeningStockAccountingPostingService
extends BasePostingService
{
    public function post(
        OpeningStock $openingStock
    ): void {
        $amount = $this->decimal(
            $openingStock->total_amount
        );

        $this->validateAmount(
            $amount
        );

        $this->createJournalEntry(
            entryDate: $openingStock->opening_date,

            voucherType: JournalVoucherTypeEnum::OPENING_STOCK->value,

            referenceType: OpeningStock::class,

            referenceId: $openingStock->id,

            description: "Opening Stock {$openingStock->document_no}",

            lines: [
                $this->debit(
                    AccountingAccounts::INVENTORY,
                    $amount
                ),

                $this->credit(
                    AccountingAccounts::OPENING_BALANCE_EQUITY,
                    $amount
                ),
            ]
        );
    }
}