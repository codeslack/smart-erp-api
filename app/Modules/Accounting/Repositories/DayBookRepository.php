<?php

namespace App\Modules\Accounting\Repositories;

use Illuminate\Support\Collection;
use App\Modules\Sales\Models\Sale;
use App\Modules\SalesReturn\Models\SalesReturn;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;
use App\Modules\Receipt\Models\Receipt;
use App\Modules\Payment\Models\Payment;
use App\Modules\Accounting\Repositories\Contracts\DayBookRepositoryInterface;

class DayBookRepository implements DayBookRepositoryInterface
{
    public function getDayBook(
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $transactions = collect();

        $this->loadSales($transactions, $fromDate, $toDate);
        $this->loadSalesReturns($transactions, $fromDate, $toDate);
        $this->loadPurchases($transactions, $fromDate, $toDate);
        $this->loadPurchaseReturns($transactions, $fromDate, $toDate);
        $this->loadReceipts($transactions, $fromDate, $toDate);
        $this->loadPayments($transactions, $fromDate, $toDate);

        return $transactions
            ->sortBy([
                ['date', 'asc'],
                ['voucher_no', 'asc'],
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
            ->when($fromDate, fn($q) => $q->whereDate('sale_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('sale_date', '<=', $toDate))
            ->get()
            ->each(function ($sale) use (&$transactions) {
                $transactions->push([
                    'date' => $sale->sale_date,
                    'voucher_no' => $sale->sale_no,
                    'party' => $sale->customer?->name,
                    'type' => 'sale',
                    'description' => 'Sales Invoice',
                    'amount' => (float) $sale->grand_total,
                ]);
            });
    }

    protected function loadSalesReturns(
        Collection &$transactions,
        ?string $fromDate,
        ?string $toDate
    ): void {
        SalesReturn::query()
            ->with('customer')
            ->when($fromDate, fn($q) => $q->whereDate('return_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('return_date', '<=', $toDate))
            ->get()
            ->each(function ($return) use (&$transactions) {
                $transactions->push([
                    'date' => $return->return_date,
                    'voucher_no' => $return->return_no,
                    'party' => $return->customer?->name,
                    'type' => 'sales_return',
                    'description' => 'Sales Return',
                    'amount' => (float) $return->grand_total,
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
            ->when($fromDate, fn($q) => $q->whereDate('purchase_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('purchase_date', '<=', $toDate))
            ->get()
            ->each(function ($purchase) use (&$transactions) {
                $transactions->push([
                    'date' => $purchase->purchase_date,
                    'voucher_no' => $purchase->purchase_no,
                    'party' => $purchase->supplier?->name,
                    'type' => 'purchase',
                    'description' => 'Purchase Invoice',
                    'amount' => (float) $purchase->grand_total,
                ]);
            });
    }

    protected function loadPurchaseReturns(
        Collection &$transactions,
        ?string $fromDate,
        ?string $toDate
    ): void {
        PurchaseReturn::query()
            ->with('supplier')
            ->when($fromDate, fn($q) => $q->whereDate('return_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('return_date', '<=', $toDate))
            ->get()
            ->each(function ($return) use (&$transactions) {
                $transactions->push([
                    'date' => $return->return_date,
                    'voucher_no' => $return->return_no,
                    'party' => $return->supplier?->name,
                    'type' => 'purchase_return',
                    'description' => 'Purchase Return',
                    'amount' => (float) $return->grand_total,
                ]);
            });
    }

    protected function loadReceipts(
        Collection &$transactions,
        ?string $fromDate,
        ?string $toDate
    ): void {
        Receipt::query()
            ->with('customer')
            ->when($fromDate, fn($q) => $q->whereDate('receipt_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('receipt_date', '<=', $toDate))
            ->get()
            ->each(function ($receipt) use (&$transactions) {
                $transactions->push([
                    'date' => $receipt->receipt_date,
                    'voucher_no' => $receipt->receipt_no,
                    'party' => $receipt->customer?->name,
                    'type' => 'receipt',
                    'description' => 'Receipt',
                    'amount' => (float) $receipt->amount,
                ]);
            });
    }

    protected function loadPayments(
        Collection &$transactions,
        ?string $fromDate,
        ?string $toDate
    ): void {
        Payment::query()
            ->with(['supplier', 'customer'])
            ->when($fromDate, fn($q) => $q->whereDate('payment_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('payment_date', '<=', $toDate))
            ->get()
            ->each(function ($payment) use (&$transactions) {
                $partyName = $payment->party_type?->isSupplier()
                    ? $payment->supplier?->name
                    : ($payment->customer?->name ?? null);

                $transactions->push([
                    'date' => $payment->payment_date,
                    'voucher_no' => $payment->payment_no,
                    'party' => $partyName,
                    'type' => $payment->payment_type instanceof \BackedEnum
                        ? $payment->payment_type->value
                        : (string) $payment->payment_type,
                    'description' => "Payment {$payment->payment_no}",
                    'amount' => (float) $payment->amount,
                ]);
            });
    }
}
