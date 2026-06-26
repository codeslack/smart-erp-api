<?php

namespace App\Modules\Accounting\Repositories;

use App\Modules\Purchase\Models\Purchase;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\SupplierPayment\Models\SupplierPaymentAllocation;
use App\Modules\Accounting\Repositories\Contracts\SupplierStatementRepositoryInterface;

class SupplierStatementRepository
implements SupplierStatementRepositoryInterface
{
    public function getStatement(
        int $supplierId,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {

        $supplier = Supplier::query()
            ->findOrFail(
                $supplierId
            );

        $transactions = collect();

        /*
        |--------------------------------------------------------------------------
        | Purchases Invoices
        |--------------------------------------------------------------------------
        */

        $purchases = Purchase::query()->with('supplier')

            ->where(
                'supplier_id',
                $supplierId
            )

            ->when(
                $fromDate,
                fn($q) => $q->whereDate(
                    'purchase_date',
                    '>=',
                    $fromDate
                )
            )

            ->when(
                $toDate,
                fn($q) => $q->whereDate(
                    'purchase_date',
                    '<=',
                    $toDate
                )
            )

            ->get();

        foreach ($purchases as $purchase) {

            $transactions->push([

                'date'
                    => $purchase->purchase_date,

                'reference'
                    => $purchase->purchase_no,

                'type'
                    => 'purchase',

                'description'
                    => "Purchase Invoice {$purchase->purchase_no}",

                'debit'
                    => (float) $purchase->grand_total,

                'credit'
                    => 0,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Payments
        |--------------------------------------------------------------------------
        */

        $allocations = SupplierPaymentAllocation::query()

            ->whereHas(
                'purchase',
                fn($q) => $q->where(
                    'supplier_id',
                    $supplierId
                )
            )

            ->whereHas(
                'payment',
                function ($q) use (
                    $fromDate,
                    $toDate
                ) {

                    $q->when(
                        $fromDate,
                        fn($query) =>
                        $query->whereDate(
                            'payment_date',
                            '>=',
                            $fromDate
                        )
                    );

                    $q->when(
                        $toDate,
                        fn($query) =>
                        $query->whereDate(
                            'payment_date',
                            '<=',
                            $toDate
                        )
                    );
                }
            )

            ->with('payment')

            ->get();

        foreach ($allocations as $allocation) {

            $transactions->push([

                'date'
                    => $allocation
                        ->payment
                        ->payment_date,

                'reference'
                    => $allocation
                        ->payment
                        ->payment_no,

                'type'
                    => 'payment',

                'description'
                    => "Supplier Payment {$allocation->payment->payment_no}",

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

            'supplier'
                => $supplier,

            'opening_balance'
                => 0,

            'transactions'
                => $transactions,

            'outstanding_balance'
                => $runningBalance,
        ];
    }
}
