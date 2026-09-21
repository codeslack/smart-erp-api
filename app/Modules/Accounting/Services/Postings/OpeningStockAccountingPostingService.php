<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;
use App\Modules\OpeningStock\Models\OpeningStock;

class OpeningStockAccountingPostingService extends BasePostingService
{
    public function post(
        OpeningStock $openingStock
    ): void {

        $amount = (float) $openingStock->total_amount;

        $this->validateAmount(
            $amount
        );

        $this->createJournalEntry(

            entryDate:
                $openingStock->opening_date,

            voucherType:
                JournalVoucherTypeEnum::OPENING_STOCK->value,

            referenceType:
                OpeningStock::class,

            referenceId:
                $openingStock->id,

            description:
                "Opening Stock {$openingStock->document_no}",

            lines: [

                [
                    'account_code' =>
                        AccountingAccounts::INVENTORY,

                    'debit' =>
                        $amount,

                    'credit' => 0,
                ],

                [
                    'account_code' =>
                        AccountingAccounts::OPENING_BALANCE_EQUITY,

                    'debit' => 0,

                    'credit' =>
                        $amount,
                ],
            ]
        );
    }
}