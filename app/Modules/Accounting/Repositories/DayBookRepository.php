<?php

namespace App\Modules\Accounting\Repositories;

use Illuminate\Support\Collection;
use App\Modules\Sales\Models\Sale;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Enums\JournalEntryStatus;
use App\Modules\CustomerReceipt\Models\CustomerReceipt;
use App\Modules\SupplierPayment\Models\SupplierPayment;
use App\Modules\Accounting\Repositories\Contracts\DayBookRepositoryInterface;

class DayBookRepository
implements DayBookRepositoryInterface
{
    public function getDayBook(
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {

        $transactions = collect();

        $this->loadSales(
            $transactions,
            $fromDate,
            $toDate
        );

        $this->loadPurchases(
            $transactions,
            $fromDate,
            $toDate
        );

        $this->loadReceipts(
            $transactions,
            $fromDate,
            $toDate
        );

        $this->loadPayments(
            $transactions,
            $fromDate,
            $toDate
        );

        // $this->loadJournalEntries(
        //     $transactions,
        //     $fromDate,
        //     $toDate
        // );

        return $transactions

            ->sortBy([
                ['date', 'asc'],
                ['voucher_no', 'asc']
            ])

            ->values()

            ->toArray();
    }


    protected function loadSales(
        Collection &$transactions,
        ?string $fromDate,
        ?string $toDate
    ): void {

        Sale::query()

            ->with('customer')

            ->when(
                $fromDate,
                fn($q) =>
                $q->whereDate(
                    'sale_date',
                    '>=',
                    $fromDate
                )
            )

            ->when(
                $toDate,
                fn($q) =>
                $q->whereDate(
                    'sale_date',
                    '<=',
                    $toDate
                )
            )

            ->get()

            ->each(function ($sale) use (
                &$transactions
            ) {

                $transactions->push([

                    'date'
                        => $sale->sale_date,

                    'voucher_no'
                        => $sale->sale_no,

                    'party'
                        => $sale->customer?->name,

                    'type'
                        => 'sale',

                    'description'
                        => 'Sales Invoice',

                    'amount'
                        => (float)
                        $sale->grand_total,
                ]);
            });
    }

    protected function loadPurchases(
        Collection &$transactions,
        ?string $fromDate,
        ?string $toDate
    ): void {

        Purchase::query()

            ->with('supplier')

            ->when(
                $fromDate,
                fn($q) =>
                $q->whereDate(
                    'purchase_date',
                    '>=',
                    $fromDate
                )
            )

            ->when(
                $toDate,
                fn($q) =>
                $q->whereDate(
                    'purchase_date',
                    '<=',
                    $toDate
                )
            )

            ->get()

            ->each(function ($purchase) use (
                &$transactions
            ) {

                $transactions->push([

                    'date'
                        => $purchase->purchase_date,

                    'voucher_no'
                        => $purchase->purchase_no,

                    'party'
                        => $purchase->supplier?->name,

                    'type'
                        => 'purchase',

                    'description'
                        => 'Purchase Invoice',

                    'amount'
                        => (float)
                        $purchase->grand_total,
                ]);
            });
    }

    protected function loadReceipts(
        Collection &$transactions,
        ?string $fromDate,
        ?string $toDate
    ): void {

        CustomerReceipt::query()

            ->with('customer')

            ->when(
                $fromDate,
                fn($q) =>
                $q->whereDate(
                    'receipt_date',
                    '>=',
                    $fromDate
                )
            )

            ->when(
                $toDate,
                fn($q) =>
                $q->whereDate(
                    'receipt_date',
                    '<=',
                    $toDate
                )
            )

            ->get()

            ->each(function ($receipt) use (
                &$transactions
            ) {

                $transactions->push([

                    'date'
                        => $receipt->receipt_date,

                    'voucher_no'
                        => $receipt->receipt_no,

                    'party'
                        => $receipt->customer?->name,

                    'type'
                        => 'receipt',

                    'description'
                        => 'Customer Receipt',

                    'amount'
                        => (float)
                        $receipt->amount,
                ]);
            });
    }

    protected function loadPayments(
        Collection &$transactions,
        ?string $fromDate,
        ?string $toDate
    ): void {

        SupplierPayment::query()

            ->with('supplier')

            ->when(
                $fromDate,
                fn($q) =>
                $q->whereDate(
                    'payment_date',
                    '>=',
                    $fromDate
                )
            )

            ->when(
                $toDate,
                fn($q) =>
                $q->whereDate(
                    'payment_date',
                    '<=',
                    $toDate
                )
            )

            ->get()

            ->each(function ($payment) use (
                &$transactions
            ) {

                $transactions->push([

                    'date'
                        => $payment->payment_date,

                    'voucher_no'
                        => $payment->payment_no,

                    'party'
                        => $payment->supplier?->name,

                    'type'
                        => 'payment',

                    'description'
                        => 'Supplier Payment',

                    'amount'
                        => (float)
                        $payment->amount,
                ]);
            });
    }

    // this is a disabled now
    protected function loadJournalEntries(
            Collection &$transactions,
            ?string $fromDate,
            ?string $toDate
        ): void {

        JournalEntry::query()

            ->where(
                'status',
                JournalEntryStatus::POSTED
            )

            ->when(
                $fromDate,
                fn($q) =>
                $q->whereDate(
                    'entry_date',
                    '>=',
                    $fromDate
                )
            )

            ->when(
                $toDate,
                fn($q) =>
                $q->whereDate(
                    'entry_date',
                    '<=',
                    $toDate
                )
            )

            ->get()

            ->each(function ($journal) use (
                &$transactions
            ) {

                $transactions->push([

                    'date'
                        => $journal->entry_date,

                    'voucher_no'
                        => $journal->voucher_no,

                    'type'
                        => 'journal',

                    'description'
                        => $journal->description,

                    'amount'
                        => (float) $journal
                            ->lines()
                            ->sum('debit'),
                ]);
            });
    }


}
