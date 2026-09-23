<?php

namespace App\Modules\Accounting\Services\Postings;

use Illuminate\Database\Eloquent\Collection;

use App\Core\Enums\OpeningBalanceTypeEnum;

use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;

use App\Modules\Customer\Models\Customer;

class CustomerOpeningBalanceAccountingPostingService extends BasePostingService
{
    public function post(Customer $customer, Collection $openingBills): void
    {
        if ($openingBills->isEmpty()) {
            return;
        }

        $receivable = 0.0;
        $customerAdvance = 0.0;

        foreach ($openingBills as $openingBill) {
            $amount = (float) $openingBill->balance_amount;

            if ($amount <= 0) {
                continue;
            }

            if ($openingBill->balance_type === OpeningBalanceTypeEnum::DEBIT) {
                $receivable += $amount;
                continue;
            }

            $customerAdvance += $amount;
        }

        if ($receivable <= 0 && $customerAdvance <= 0) {
            return;
        }

        $lines = [];

        /*
         * Customer owes us.
         */
        if ($receivable > 0) {
            $lines[] = $this->debit(
                AccountingAccounts::ACCOUNTS_RECEIVABLE,
                $receivable
            );
        }

        /*
         * Customer has an advance with us.
         */
        if ($customerAdvance > 0) {
            $lines[] = $this->credit(
                AccountingAccounts::CUSTOMER_ADVANCES,
                $customerAdvance
            );
        }

        /*
         * Opening Balance Equity is the balancing account.
         *
         * Examples:
         *
         * Receivable 5,000 + Advance 500:
         *   Dr AR                    5,000
         *   Cr Customer Advances       500
         *   Cr Opening Balance Equity 4,500
         *
         * Receivable 500 + Advance 5,000:
         *   Dr AR                      500
         *   Dr Opening Balance Equity 4,500
         *   Cr Customer Advances     5,000
         */
        $equityDifference = $receivable - $customerAdvance;

        if ($equityDifference > 0) {
            $lines[] = $this->credit(
                AccountingAccounts::OPENING_BALANCE_EQUITY,
                $equityDifference
            );
        } elseif ($equityDifference < 0) {
            $lines[] = $this->debit(
                AccountingAccounts::OPENING_BALANCE_EQUITY,
                abs($equityDifference)
            );
        }

        $this->createJournalEntry(
            entryDate: 
                $openingBills
                    ->min('bill_date')
                    ->toDateString(),

            voucherType: 
                JournalVoucherTypeEnum::CUSTOMER_OPENING_BALANCE->value,

            referenceType: Customer::class,

            referenceId: $customer->id,

            description: "Customer Opening Balance {$customer->name}",
            
            lines: $lines
        );
    }
}