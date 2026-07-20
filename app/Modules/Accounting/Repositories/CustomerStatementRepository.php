<?php

namespace App\Modules\Accounting\Repositories;

use Illuminate\Support\Collection;
use App\Modules\Sales\Models\Sale;
use App\Modules\Customer\Models\Customer;
use App\Modules\CustomerReceipt\Models\CustomerReceipt;
use App\Modules\CustomerReceipt\Enums\CustomerReceiptStatus;
use App\Modules\Accounting\Repositories\Contracts\CustomerStatementRepositoryInterface;

class CustomerStatementRepository
implements CustomerStatementRepositoryInterface
{
    public function getStatement(
        int $customerId,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {

        $customer = Customer::query()
            ->findOrFail($customerId);

        $transactions = collect();

        $this->loadInvoices(
            $transactions,
            $customerId,
            $fromDate,
            $toDate
        );

        $this->loadReceipts(
            $transactions,
            $customerId,
            $fromDate,
            $toDate
        );

        $transactions = $this->sortTransactions(
            $transactions
        );

        $runningBalance =
            $this->applyRunningBalance(
                $transactions
            );

        return [

            'customer' =>
                $customer,

            'opening_balance' =>
                0,

            'transactions' =>
                $transactions,

            'outstanding_balance' =>
                $runningBalance,
        ];
    }

    private function loadInvoices(
        Collection $transactions,
        int $customerId,
        ?string $fromDate,
        ?string $toDate
    ): void {

        Sale::query()

            ->where(
                'customer_id',
                $customerId
            )

            ->when(
                $fromDate,
                fn ($q) =>
                    $q->whereDate(
                        'sale_date',
                        '>=',
                        $fromDate
                    )
            )

            ->when(
                $toDate,
                fn ($q) =>
                    $q->whereDate(
                        'sale_date',
                        '<=',
                        $toDate
                    )
            )

            ->get()

            ->each(function (
                Sale $sale
            ) use (
                $transactions
            ) {

                $transactions->push([

                    'date' =>
                        $sale->sale_date,

                    'reference' =>
                        $sale->sale_no,

                    'type' =>
                        'invoice',

                    'description' =>
                        "Sale Invoice {$sale->sale_no}",

                    'debit' =>
                        (float) $sale->grand_total,

                    'credit' =>
                        0,
                ]);
            });
    }

    private function loadReceipts(
        Collection $transactions,
        int $customerId,
        ?string $fromDate,
        ?string $toDate
    ): void {

        CustomerReceipt::query()

            ->where(
                'customer_id',
                $customerId
            )

            ->where(
                'status',
                CustomerReceiptStatus::CONFIRMED
            )

            ->when(
                $fromDate,
                fn ($q) =>
                    $q->whereDate(
                        'receipt_date',
                        '>=',
                        $fromDate
                    )
            )

            ->when(
                $toDate,
                fn ($q) =>
                    $q->whereDate(
                        'receipt_date',
                        '<=',
                        $toDate
                    )
            )

            ->get()

            ->each(function (
                CustomerReceipt $receipt
            ) use (
                $transactions
            ) {

                $transactions->push([

                    'date' =>
                        $receipt->receipt_date,

                    'reference' =>
                        $receipt->receipt_no,

                    'type' =>
                        $receipt->receipt_type->value,

                    'description' =>
                        "Customer Receipt {$receipt->receipt_no}",

                    'debit' =>
                        0,

                    'credit' =>
                        (float) $receipt->amount,
                ]);
            });
    }

    private function sortTransactions(
        Collection $transactions
    ): Collection {

        return $transactions

            ->sortBy([
                ['date', 'asc'],
                ['reference', 'asc'],
            ])

            ->values();
    }

    private function applyRunningBalance(
        Collection $transactions
    ): float {

        $balance = 0;

        $transactions->transform(

            function (
                array $row
            ) use (
                &$balance
            ) {

                $balance +=
                    $row['debit'];

                $balance -=
                    $row['credit'];

                $row['balance'] =
                    $balance;

                return $row;
            }
        );

        return $balance;
    }
}
