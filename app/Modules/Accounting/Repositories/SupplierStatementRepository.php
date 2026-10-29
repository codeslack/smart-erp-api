<?php

namespace App\Modules\Accounting\Repositories;

use Illuminate\Support\Collection;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Enums\PurchaseStatusEnum;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnStatus;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Enums\PaymentPartyTypeEnum;
use App\Modules\Payment\Enums\PaymentStatusEnum;
use App\Modules\Accounting\Repositories\Contracts\SupplierStatementRepositoryInterface;

class SupplierStatementRepository implements SupplierStatementRepositoryInterface
{
    public function getStatement(
        int $supplierId,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $supplier = Supplier::query()->findOrFail($supplierId);

        $openingBalance = $this->calculateOpeningBalance($supplierId, $fromDate);

        $transactions = collect();

        $this->loadPurchases(
            $transactions,
            $supplierId,
            $fromDate,
            $toDate
        );

        $this->loadPurchaseReturns(
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

        $transactions = $this->sortTransactions($transactions);

        $runningBalance = $this->applyRunningBalance(
            $transactions,
            $openingBalance
        );

        return [
            'supplier' => $supplier,
            'opening_balance' => $openingBalance,
            'transactions' => $transactions,
            'outstanding_balance' => $runningBalance,
        ];
    }

    private function calculateOpeningBalance(int $supplierId, ?string $fromDate): float
    {
        if (! $fromDate) {
            return 0.0;
        }

        $priorPurchases = (float) Purchase::query()
            ->where('supplier_id', $supplierId)
            ->where('status', PurchaseStatusEnum::CONFIRMED)
            ->whereDate('purchase_date', '<', $fromDate)
            ->sum('grand_total');

        $priorReturns = (float) PurchaseReturn::query()
            ->where('supplier_id', $supplierId)
            ->where('status', PurchaseReturnStatus::CONFIRMED)
            ->whereDate('return_date', '<', $fromDate)
            ->sum('grand_total');

        $priorPayments = (float) Payment::query()
            ->where('party_type', PaymentPartyTypeEnum::SUPPLIER)
            ->where('party_id', $supplierId)
            ->where('status', PaymentStatusEnum::CONFIRMED)
            ->whereDate('payment_date', '<', $fromDate)
            ->sum('amount');

        return $priorPurchases - $priorReturns - $priorPayments;
    }

    private function loadPurchases(
        Collection $transactions,
        int $supplierId,
        ?string $fromDate,
        ?string $toDate
    ): void {
        Purchase::query()
            ->where('supplier_id', $supplierId)
            ->where('status', PurchaseStatusEnum::CONFIRMED)
            ->when($fromDate, fn($q) => $q->whereDate('purchase_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('purchase_date', '<=', $toDate))
            ->get()
            ->each(function (Purchase $purchase) use ($transactions) {
                $transactions->push([
                    'date' => $purchase->purchase_date,
                    'reference' => $purchase->purchase_no,
                    'type' => 'purchase',
                    'description' => "Purchase {$purchase->purchase_no}",
                    'debit' => 0,
                    'credit' => (float) $purchase->grand_total,
                ]);
            });
    }

    private function loadPurchaseReturns(
        Collection $transactions,
        int $supplierId,
        ?string $fromDate,
        ?string $toDate
    ): void {
        PurchaseReturn::query()
            ->where('supplier_id', $supplierId)
            ->where('status', PurchaseReturnStatus::CONFIRMED)
            ->when($fromDate, fn($q) => $q->whereDate('return_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('return_date', '<=', $toDate))
            ->get()
            ->each(function (PurchaseReturn $return) use ($transactions) {
                $transactions->push([
                    'date' => $return->return_date,
                    'reference' => $return->return_no,
                    'type' => 'purchase_return',
                    'description' => "Purchase Return {$return->return_no}",
                    'debit' => (float) $return->grand_total,
                    'credit' => 0,
                ]);
            });
    }

    private function loadPayments(
        Collection $transactions,
        int $supplierId,
        ?string $fromDate,
        ?string $toDate
    ): void {
        Payment::query()
            ->where('party_type', PaymentPartyTypeEnum::SUPPLIER)
            ->where('party_id', $supplierId)
            ->where('status', PaymentStatusEnum::CONFIRMED)
            ->when($fromDate, fn($q) => $q->whereDate('payment_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('payment_date', '<=', $toDate))
            ->get()
            ->each(function (Payment $payment) use ($transactions) {
                $transactions->push([
                    'date' => $payment->payment_date,
                    'reference' => $payment->payment_no,
                    'type' => 'payment',
                    'description' => "Payment {$payment->payment_no}",
                    'debit' => (float) $payment->amount,
                    'credit' => 0,
                ]);
            });
    }

    private function sortTransactions(Collection $transactions): Collection
    {
        return $transactions
            ->sortBy([
                ['date', 'asc'],
                ['reference', 'asc'],
            ])
            ->values();
    }

    private function applyRunningBalance(
        Collection $transactions,
        float $initialBalance = 0.0
    ): float {
        $balance = $initialBalance;

        $transactions->transform(function (array $row) use (&$balance) {
            $balance += $row['credit'];
            $balance -= $row['debit'];
            $row['balance'] = $balance;

            return $row;
        });

        return $balance;
    }
}
