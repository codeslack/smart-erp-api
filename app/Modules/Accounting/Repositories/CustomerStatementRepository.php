<?php

namespace App\Modules\Accounting\Repositories;

use App\Modules\Sales\Models\Sale;
use App\Modules\Customer\Models\Customer;
use App\Modules\CustomerReceipt\Models\CustomerReceiptAllocation;
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
            ->findOrFail(
                $customerId
            );

        $transactions = collect();

        /*
        |--------------------------------------------------------------------------
        | Sales Invoices
        |--------------------------------------------------------------------------
        */

        $sales = Sale::query()->with('customer')

            ->where(
                'customer_id',
                $customerId
            )

            ->when(
                $fromDate,
                fn($q) => $q->whereDate(
                    'sale_date',
                    '>=',
                    $fromDate
                )
            )

            ->when(
                $toDate,
                fn($q) => $q->whereDate(
                    'sale_date',
                    '<=',
                    $toDate
                )
            )

            ->get();

        foreach ($sales as $sale) {

            $transactions->push([

                'date'
                    => $sale->sale_date,

                'reference'
                    => $sale->sale_no,

                'type'
                    => 'invoice',

                'description'
                    => "Sale Invoice {$sale->sale_no}",

                'debit'
                    => (float) $sale->grand_total,

                'credit'
                    => 0,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Receipts
        |--------------------------------------------------------------------------
        */

        $allocations = CustomerReceiptAllocation::query()

            ->whereHas(
                'sale',
                fn($q) => $q->where(
                    'customer_id',
                    $customerId
                )
            )

            ->whereHas(
                'receipt',
                function ($q) use (
                    $fromDate,
                    $toDate
                ) {

                    $q->when(
                        $fromDate,
                        fn($query) =>
                        $query->whereDate(
                            'receipt_date',
                            '>=',
                            $fromDate
                        )
                    );

                    $q->when(
                        $toDate,
                        fn($query) =>
                        $query->whereDate(
                            'receipt_date',
                            '<=',
                            $toDate
                        )
                    );
                }
            )

            ->with('receipt')

            ->get();

        foreach ($allocations as $allocation) {

            $transactions->push([

                'date'
                    => $allocation
                        ->receipt
                        ->receipt_date,

                'reference'
                    => $allocation
                        ->receipt
                        ->receipt_no,

                'type'
                    => 'receipt',

                'description'
                    => "Customer Receipt {$allocation->receipt->receipt_no}",

                'debit'
                    => 0,

                'credit'
                    => (float) $allocation->allocated_amount,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Sort
        |--------------------------------------------------------------------------
        */

        $transactions = $transactions

            ->sortBy([
                ['date', 'asc'],
                ['reference', 'asc']
            ])

            ->values();

        /*
        |--------------------------------------------------------------------------
        | Running Balance
        |--------------------------------------------------------------------------
        */

        $runningBalance = 0;

        $transactions = $transactions

            ->map(function (
                $row
            ) use (
                &$runningBalance
            ) {

                $runningBalance +=
                    $row['debit'];

                $runningBalance -=
                    $row['credit'];

                $row['balance']
                    = $runningBalance;

                return $row;
            });

        return [

            'customer'
                => $customer,

            'opening_balance'
                => 0,

            'transactions'
                => $transactions,

            'outstanding_balance'
                => $runningBalance,
        ];
    }
}
