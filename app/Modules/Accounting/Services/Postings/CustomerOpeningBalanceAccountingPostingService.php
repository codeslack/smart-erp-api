<?php

namespace App\Modules\Accounting\Services\Postings;

use Illuminate\Database\Eloquent\Collection;

use App\Core\Enums\OpeningBalanceTypeEnum;

use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;

use App\Modules\Customer\Models\Customer;

class CustomerOpeningBalanceAccountingPostingService
    extends BasePostingService
{
    private const SCALE = 4;

    public function post(
        Customer $customer,
        Collection $openingBills
    ): void {

        if ($openingBills->isEmpty()) {
            return;
        }

        $receivable = '0.0000';
        $customerAdvance = '0.0000';

        foreach ($openingBills as $openingBill) {
            $amount = bcadd(
                (string) $openingBill->balance_amount,
                '0',
                self::SCALE
            );

            if (bccomp($amount, '0', self::SCALE) <= 0) {
                continue;
            }

            if (
                $openingBill->balance_type
                === OpeningBalanceTypeEnum::DEBIT
            ) {
                $receivable = bcadd(
                    $receivable,
                    $amount,
                    self::SCALE
                );

                continue;
            }

            $customerAdvance = bcadd(
                $customerAdvance,
                $amount,
                self::SCALE
            );
        }

        if (
            bccomp($receivable, '0', self::SCALE) === 0
            && bccomp($customerAdvance, '0', self::SCALE) === 0
        ) {
            return;
        }

        $lines = [];

        /*
         * Customer owes us.
         */
        if (
            bccomp($receivable, '0', self::SCALE) > 0
        ) {
            $lines[] = $this->debit(
                AccountingAccounts::ACCOUNTS_RECEIVABLE,
                $receivable
            );
        }

        /*
         * Customer has an advance with us.
         */
        if (
            bccomp($customerAdvance, '0', self::SCALE) > 0
        ) {
            $lines[] = $this->credit(
                AccountingAccounts::CUSTOMER_ADVANCES,
                $customerAdvance
            );
        }

        /*
         * Opening Balance Equity is the balancing account.
         */
        $equityDifference = bcsub(
            $receivable,
            $customerAdvance,
            self::SCALE
        );

        if (
            bccomp($equityDifference, '0', self::SCALE) > 0
        ) {
            $lines[] = $this->credit(
                AccountingAccounts::OPENING_BALANCE_EQUITY,
                $equityDifference
            );
        } elseif (
            bccomp($equityDifference, '0', self::SCALE) < 0
        ) {
            $lines[] = $this->debit(
                AccountingAccounts::OPENING_BALANCE_EQUITY,
                bcmul(
                    $equityDifference,
                    '-1',
                    self::SCALE
                )
            );
        }

        $this->createJournalEntry(
            entryDate: $openingBills
                ->min('bill_date')
                ->toDateString(),

            voucherType:
                JournalVoucherTypeEnum::CUSTOMER_OPENING_BALANCE
                    ->value,

            referenceType: Customer::class,

            referenceId: $customer->id,

            description:
                "Customer Opening Balance {$customer->name}",

            lines: $lines
        );
    }
}