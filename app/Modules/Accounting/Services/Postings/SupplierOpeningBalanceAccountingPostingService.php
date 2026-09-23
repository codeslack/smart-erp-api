<?php
// app/Modules/Accounting/Services/Postings/SupplierOpeningBalanceAccountingPostingService.php

namespace App\Modules\Accounting\Services\Postings;

use Illuminate\Database\Eloquent\Collection;

use App\Core\Enums\OpeningBalanceTypeEnum;

use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;

use App\Modules\Supplier\Models\Supplier;

class SupplierOpeningBalanceAccountingPostingService
    extends BasePostingService
{
    public function post(
        Supplier $supplier,
        Collection $openingBills
    ): void {
        if ($openingBills->isEmpty()) {
            return;
        }

        $payable = 0.0;
        $supplierAdvance = 0.0;

        foreach ($openingBills as $openingBill) {
            $amount = (float) $openingBill->balance_amount;

            if ($amount <= 0) {
                continue;
            }

            /*
             * Supplier CREDIT balance:
             * We owe the supplier.
             */
            if (
                $openingBill->balance_type
                === OpeningBalanceTypeEnum::CREDIT
            ) {
                $payable += $amount;

                continue;
            }

            /*
             * Supplier DEBIT balance:
             * Supplier owes us / supplier advance.
             */
            $supplierAdvance += $amount;
        }

        if (
            $payable <= 0
            &&
            $supplierAdvance <= 0
        ) {
            return;
        }

        $lines = [];

        /*
         * We owe the supplier.
         */
        if ($payable > 0) {
            $lines[] = $this->credit(
                AccountingAccounts::ACCOUNTS_PAYABLE,
                $payable
            );
        }

        /*
         * Supplier has an advance with us.
         */
        if ($supplierAdvance > 0) {
            $lines[] = $this->debit(
                AccountingAccounts::SUPPLIER_ADVANCES,
                $supplierAdvance
            );
        }

        /*
         * Opening Balance Equity is the
         * balancing account.
         *
         * Examples:
         *
         * Payable 5,000 + Advance 500:
         *
         *   Dr Supplier Advances          500
         *   Dr Opening Balance Equity   4,500
         *   Cr Accounts Payable         5,000
         *
         * Payable 500 + Advance 5,000:
         *
         *   Dr Supplier Advances        5,000
         *   Cr Accounts Payable           500
         *   Cr Opening Balance Equity   4,500
         */
        $equityDifference =
            $payable
            - $supplierAdvance;

        if ($equityDifference > 0) {
            $lines[] = $this->debit(
                AccountingAccounts::OPENING_BALANCE_EQUITY,
                $equityDifference
            );
        } elseif ($equityDifference < 0) {
            $lines[] = $this->credit(
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
                JournalVoucherTypeEnum::SUPPLIER_OPENING_BALANCE->value,

            referenceType:
                Supplier::class,

            referenceId:
                $supplier->id,

            description:
                "Supplier Opening Balance {$supplier->name}",

            lines:
                $lines
        );
    }
}