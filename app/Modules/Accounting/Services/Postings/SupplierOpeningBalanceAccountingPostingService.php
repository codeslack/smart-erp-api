<?php

namespace App\Modules\Accounting\Services\Postings;

use Illuminate\Database\Eloquent\Collection;

use App\Core\Enums\OpeningBalanceTypeEnum;

use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;

use App\Modules\Supplier\Models\Supplier;

class SupplierOpeningBalanceAccountingPostingService
    extends BasePostingService
{
    private const SCALE = 4;

    public function post(
        Supplier $supplier,
        Collection $openingBills
    ): void {
        if ($openingBills->isEmpty()) {
            return;
        }

        $payable = '0.0000';
        $supplierAdvance = '0.0000';

        foreach ($openingBills as $openingBill) {
            $amount = bcadd(
                (string) $openingBill->balance_amount,
                '0',
                self::SCALE
            );

            if (
                bccomp($amount, '0', self::SCALE) <= 0
            ) {
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
                $payable = bcadd(
                    $payable,
                    $amount,
                    self::SCALE
                );

                continue;
            }

            /*
             * Supplier DEBIT balance:
             * Supplier owes us / supplier advance.
             */
            $supplierAdvance = bcadd(
                $supplierAdvance,
                $amount,
                self::SCALE
            );
        }

        if (
            bccomp($payable, '0', self::SCALE) === 0
            &&
            bccomp($supplierAdvance, '0', self::SCALE) === 0
        ) {
            return;
        }

        $lines = [];

        /*
         * We owe the supplier.
         */
        if (
            bccomp($payable, '0', self::SCALE) > 0
        ) {
            $lines[] = $this->credit(
                AccountingAccounts::ACCOUNTS_PAYABLE,
                $payable
            );
        }

        /*
         * Supplier has an advance with us.
         */
        if (
            bccomp($supplierAdvance, '0', self::SCALE) > 0
        ) {
            $lines[] = $this->debit(
                AccountingAccounts::SUPPLIER_ADVANCES,
                $supplierAdvance
            );
        }

        /*
         * Opening Balance Equity is the
         * balancing account.
         */
        $equityDifference = bcsub(
            $payable,
            $supplierAdvance,
            self::SCALE
        );

        if (
            bccomp($equityDifference, '0', self::SCALE) > 0
        ) {
            $lines[] = $this->debit(
                AccountingAccounts::OPENING_BALANCE_EQUITY,
                $equityDifference
            );
        } elseif (
            bccomp($equityDifference, '0', self::SCALE) < 0
        ) {
            $lines[] = $this->credit(
                AccountingAccounts::OPENING_BALANCE_EQUITY,
                bcmul(
                    $equityDifference,
                    '-1',
                    self::SCALE
                )
            );
        }

        $this->createJournalEntry(
            entryDate:
                $openingBills
                    ->min('bill_date')
                    ->toDateString(),

            voucherType:
                JournalVoucherTypeEnum::SUPPLIER_OPENING_BALANCE
                    ->value,

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