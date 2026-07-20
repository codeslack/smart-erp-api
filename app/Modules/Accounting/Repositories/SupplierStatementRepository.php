<?php

namespace App\Modules\Accounting\Repositories;

use Illuminate\Support\Collection;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\SupplierPayment\Models\SupplierPayment;
use App\Modules\SupplierPayment\Enums\SupplierPaymentStatus;
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
            ->findOrFail($supplierId);

        $transactions = collect();

        $this->loadPurchases(
            $transactions,
            $supplierId,
            $fromDate,
            $toDate
        );

        $this->loadPayments(
            $transactions,
            $supplierId,
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

            'supplier' =>
                $supplier,

            'opening_balance' =>
                0,

            'transactions' =>
                $transactions,

            'outstanding_balance' =>
                $runningBalance,
        ];
    }

    private function loadPurchases(
        Collection $transactions,
        int $supplierId,
        ?string $fromDate,
        ?string $toDate
    ): void {

        Purchase::query()

            ->where(
                'supplier_id',
                $supplierId
            )

            ->when(
                $fromDate,
                fn ($q) =>
                    $q->whereDate(
                        'purchase_date',
                        '>=',
                        $fromDate
                    )
            )

            ->when(
                $toDate,
                fn ($q) =>
                    $q->whereDate(
                        'purchase_date',
                        '<=',
                        $toDate
                    )
            )

            ->get()

            ->each(function (
                Purchase $purchase
            ) use (
                $transactions
            ) {

                $transactions->push([

                    'date' =>
                        $purchase->purchase_date,

                    'reference' =>
                        $purchase->purchase_no,

                    'type' =>
                        'purchase',

                    'description' =>
                        "Purchase {$purchase->purchase_no}",

                    'debit' =>
                        0,

                    'credit' =>
                        (float) $purchase->grand_total,
                ]);
            });
    }

    private function loadPayments(
        Collection $transactions,
        int $supplierId,
        ?string $fromDate,
        ?string $toDate
    ): void {

        SupplierPayment::query()

            ->where(
                'supplier_id',
                $supplierId
            )

            ->where(
                'status',
                SupplierPaymentStatus::CONFIRMED
            )

            ->when(
                $fromDate,
                fn ($q) =>
                    $q->whereDate(
                        'payment_date',
                        '>=',
                        $fromDate
                    )
            )

            ->when(
                $toDate,
                fn ($q) =>
                    $q->whereDate(
                        'payment_date',
                        '<=',
                        $toDate
                    )
            )

            ->get()

            ->each(function (
                SupplierPayment $payment
            ) use (
                $transactions
            ) {

                $transactions->push([

                    'date' =>
                        $payment->payment_date,

                    'reference' =>
                        $payment->payment_no,

                    'type' =>
                        'payment',

                    'description' =>
                        "Supplier Payment {$payment->payment_no}",

                    'debit' =>
                        (float) $payment->amount,

                    'credit' =>
                        0,
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
                    $row['credit'];

                $balance -=
                    $row['debit'];

                $row['balance'] =
                    $balance;

                return $row;
            }
        );

        return $balance;
    }
}