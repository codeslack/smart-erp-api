<?php

namespace App\Modules\Accounting\Repositories;

use Illuminate\Support\Collection;

use App\Modules\Sales\Models\Sale;
use App\Modules\SalesReturn\Models\SalesReturn;
use App\Modules\Customer\Models\Customer;

use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Enums\PaymentPartyTypeEnum;
use App\Modules\Payment\Enums\PaymentStatusEnum;
use App\Modules\Payment\Enums\PaymentTypeEnum;

use App\Modules\Accounting\Repositories\Contracts\CustomerStatementRepositoryInterface;

class CustomerStatementRepository implements CustomerStatementRepositoryInterface
{
    public function getStatement(
        int $customerId,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $customer = Customer::query()->findOrFail($customerId);

        $openingBalance = $this->calculateOpeningBalance(
            $customerId,
            $fromDate
        );

        $transactions = collect();

        $this->loadInvoices(
            $transactions,
            $customerId,
            $fromDate,
            $toDate
        );

        $this->loadSalesReturns(
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

        $transactions = $this->sortTransactions($transactions);

        $runningBalance = $this->applyRunningBalance(
            $transactions,
            $openingBalance
        );

        return [
            'customer' => $customer,
            'opening_balance' => $openingBalance,
            'transactions' => $transactions,
            'outstanding_balance' => $runningBalance,
        ];
    }

    private function calculateOpeningBalance(
        int $customerId,
        ?string $fromDate
    ): float {
        if (! $fromDate) {
            return 0.0;
        }

        $priorSales = (float) Sale::query()
            ->where('customer_id', $customerId)
            ->whereDate('sale_date', '<', $fromDate)
            ->sum('grand_total');

        $priorReturns = (float) SalesReturn::query()
            ->where('customer_id', $customerId)
            ->whereDate('return_date', '<', $fromDate)
            ->sum('grand_total');

        $priorReceipts = (float) Payment::query()
            ->where('party_type', PaymentPartyTypeEnum::CUSTOMER)
            ->where('party_id', $customerId)
            ->where('payment_type', PaymentTypeEnum::RECEIPT)
            ->where('status', PaymentStatusEnum::CONFIRMED)
            ->whereDate('payment_date', '<', $fromDate)
            ->sum('amount');

        return $priorSales - $priorReturns - $priorReceipts;
    }

    private function loadInvoices(
        Collection $transactions,
        int $customerId,
        ?string $fromDate,
        ?string $toDate
    ): void {
        Sale::query()
            ->where('customer_id', $customerId)
            ->when(
                $fromDate,
                fn($q) => $q->whereDate('sale_date', '>=', $fromDate)
            )
            ->when(
                $toDate,
                fn($q) => $q->whereDate('sale_date', '<=', $toDate)
            )
            ->get()
            ->each(function (Sale $sale) use ($transactions) {
                $transactions->push([
                    'date' => $sale->sale_date,
                    'reference' => $sale->sale_no,
                    'type' => 'invoice',
                    'description' => "Sale Invoice {$sale->sale_no}",
                    'debit' => (float) $sale->grand_total,
                    'credit' => 0,
                ]);
            });
    }

    private function loadSalesReturns(
        Collection $transactions,
        int $customerId,
        ?string $fromDate,
        ?string $toDate
    ): void {
        SalesReturn::query()
            ->where('customer_id', $customerId)
            ->when(
                $fromDate,
                fn($q) => $q->whereDate('return_date', '>=', $fromDate)
            )
            ->when(
                $toDate,
                fn($q) => $q->whereDate('return_date', '<=', $toDate)
            )
            ->get()
            ->each(function (SalesReturn $return) use ($transactions) {
                $transactions->push([
                    'date' => $return->return_date,
                    'reference' => $return->return_no,
                    'type' => 'sales_return',
                    'description' => "Sales Return {$return->return_no}",
                    'debit' => 0,
                    'credit' => (float) $return->grand_total,
                ]);
            });
    }

    private function loadReceipts(
        Collection $transactions,
        int $customerId,
        ?string $fromDate,
        ?string $toDate
    ): void {
        Payment::query()
            ->where('party_type', PaymentPartyTypeEnum::CUSTOMER)
            ->where('party_id', $customerId)
            ->where('payment_type', PaymentTypeEnum::RECEIPT)
            ->where('status', PaymentStatusEnum::CONFIRMED)
            ->when(
                $fromDate,
                fn($q) => $q->whereDate('payment_date', '>=', $fromDate)
            )
            ->when(
                $toDate,
                fn($q) => $q->whereDate('payment_date', '<=', $toDate)
            )
            ->get()
            ->each(function (Payment $payment) use ($transactions) {
                $transactions->push([
                    'date' => $payment->payment_date,
                    'reference' => $payment->payment_no,
                    'type' => 'receipt',
                    'description' => "Receipt {$payment->payment_no}",
                    'debit' => 0,
                    'credit' => (float) $payment->amount,
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
            $balance += $row['debit'];
            $balance -= $row['credit'];

            $row['balance'] = $balance;

            return $row;
        });

        return $balance;
    }
}