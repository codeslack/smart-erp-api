<?php

namespace App\Modules\Accounting\Repositories;

use Illuminate\Support\Collection;

use App\Core\Enums\OpeningBalanceTypeEnum;
use App\Modules\Accounting\Repositories\Contracts\CustomerStatementRepositoryInterface;
use App\Modules\Customer\Models\Customer;
use App\Modules\CustomerOpeningBill\Models\CustomerOpeningBill;
use App\Modules\Payment\Enums\PaymentPartyTypeEnum;
use App\Modules\Payment\Enums\PaymentPurposeEnum;
use App\Modules\Payment\Enums\PaymentStatusEnum;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Sales\Enums\SaleStatusEnum;
use App\Modules\Sales\Models\Sale;
use App\Modules\SalesReturn\Enums\SalesReturnStatus;
use App\Modules\SalesReturn\Models\SalesReturn;
use App\Modules\Settings\Models\Setting;

class CustomerStatementRepository implements CustomerStatementRepositoryInterface
{
    private const OPENING_BILL_TYPE = 'customer_opening_bill';

    private const POSTED_SALE_STATUSES = [
        'confirmed',
        'partially_paid',
        'paid',
    ];

    private const CUSTOMER_PAYMENT_PURPOSES = [
        'customer_payment',
        'customer_advance',
        'customer_opening_balance_adjustment',
        'customer_refund',
    ];

    public function getStatement(
        int $customerId,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $customer = Customer::query()->findOrFail($customerId);
        $baseCurrency = $this->baseCurrencyCode();

        $openingBalance = $this->calculateOpeningBalance(
            $customerId,
            $fromDate,
            $toDate,
        );

        $transactions = collect();

        $this->loadOpeningBills($transactions, $customerId, $fromDate, $toDate, $baseCurrency);
        $this->loadInvoices($transactions, $customerId, $fromDate, $toDate);
        $this->loadSalesReturns($transactions, $customerId, $fromDate, $toDate);
        $this->loadPayments($transactions, $customerId, $fromDate, $toDate, $baseCurrency);

        $transactions = $this->sortTransactions($transactions);

        $runningBalance = $this->applyRunningBalance(
            $transactions,
            $openingBalance
        );

        return [
            'customer' => $customer,
            'base_currency_code' => $baseCurrency,
            'opening_balance' => $openingBalance,
            'transactions' => $transactions,
            'outstanding_balance' => $runningBalance,
        ];
    }

    private function calculateOpeningBalance(
        int $customerId,
        ?string $fromDate,
        ?string $toDate
    ): float {
        $openingBalance = 0.0;

        $openingBills = CustomerOpeningBill::query()
            ->where('customer_id', $customerId)
            ->when(
                $fromDate,
                fn ($query) => $query->whereDate('bill_date', '<', $fromDate)
            )
            ->when(
                $toDate,
                fn ($query) => $query->whereDate('bill_date', '<=', $toDate)
            )
            ->get();

        foreach ($openingBills as $bill) {
            // Payment allocations reduce the live opening-bill balance.
            // Add back allocations from the statement start onward so those
            // payments can appear once as dated statement transactions.
            $balanceAtStart = (float) $bill->balance_amount
                + $this->allocatedAmountSince(
                    self::OPENING_BILL_TYPE,
                    (int) $bill->getKey(),
                    $fromDate,
                );

            $openingBalance += $bill->balance_type === OpeningBalanceTypeEnum::DEBIT
                ? $balanceAtStart
                : -$balanceAtStart;
        }

        if ($fromDate === null) {
            return round($openingBalance, 4);
        }

        $priorSales = Sale::query()
            ->whereIn('status', self::POSTED_SALE_STATUSES)
            ->where('customer_id', $customerId)
            ->whereDate('sale_date', '<', $fromDate)
            ->get()
            ->sum(fn (Sale $sale) => $this->baseAmount(
                $sale->grand_total,
                $sale->exchange_rate,
            ));

        $priorReturns = SalesReturn::query()
            ->where('status', SalesReturnStatus::CONFIRMED->value)
            ->where('customer_id', $customerId)
            ->whereDate('return_date', '<', $fromDate)
            ->get()
            ->sum(fn (SalesReturn $return) => $this->baseAmount(
                $return->grand_total,
                $return->exchange_rate,
            ));

        $priorPayments = $this->confirmedPayments($customerId)
            ->whereDate('payment_date', '<', $fromDate)
            ->get();

        foreach ($priorPayments as $payment) {
            if ($payment->purpose === PaymentPurposeEnum::CUSTOMER_REFUND) {
                $openingBalance += (float) $payment->amount;
            } else {
                $openingBalance -= (float) $payment->amount;
            }
        }

        return round(
            $openingBalance + $priorSales - $priorReturns,
            4
        );
    }

    private function loadOpeningBills(
        Collection $transactions,
        int $customerId,
        ?string $fromDate,
        ?string $toDate,
        string $baseCurrency
    ): void {
        if ($fromDate === null) {
            return;
        }

        CustomerOpeningBill::query()
            ->where('customer_id', $customerId)
            ->whereDate('bill_date', '>=', $fromDate)
            ->when($toDate, fn ($query) => $query->whereDate('bill_date', '<=', $toDate))
            ->get()
            ->each(function (CustomerOpeningBill $bill) use ($transactions, $baseCurrency) {
                $amount = (float) $bill->balance_amount
                    + $this->allocatedAmountSince(
                        self::OPENING_BILL_TYPE,
                        (int) $bill->getKey(),
                        $bill->bill_date?->toDateString(),
                    );

                $debit = $bill->balance_type === OpeningBalanceTypeEnum::DEBIT
                    ? $amount
                    : 0.0;
                $credit = $bill->balance_type === OpeningBalanceTypeEnum::CREDIT
                    ? $amount
                    : 0.0;

                $transactions->push([
                    'date' => $bill->bill_date,
                    'reference' => $bill->bill_no,
                    'type' => 'opening_balance',
                    'description' => "Opening Bill {$bill->bill_no}",
                    'debit' => $debit,
                    'credit' => $credit,
                    'currency_code' => $baseCurrency,
                    'exchange_rate' => 1.0,
                    'document_amount' => round($amount, 4),
                    'base_amount' => round($amount, 4),
                ]);
            });
    }

    private function loadInvoices(
        Collection $transactions,
        int $customerId,
        ?string $fromDate,
        ?string $toDate
    ): void {
        Sale::query()
            ->whereIn('status', self::POSTED_SALE_STATUSES)
            ->where('customer_id', $customerId)
            ->when($fromDate, fn ($query) => $query->whereDate('sale_date', '>=', $fromDate))
            ->when($toDate, fn ($query) => $query->whereDate('sale_date', '<=', $toDate))
            ->get()
            ->each(function (Sale $sale) use ($transactions) {
                $meta = $this->currencyMeta(
                    $sale->currency_code,
                    $sale->exchange_rate,
                    (float) $sale->grand_total,
                );

                $transactions->push([
                    'date' => $sale->sale_date,
                    'reference' => $sale->sale_no,
                    'type' => 'invoice',
                    'description' => "Sale Invoice {$sale->sale_no}",
                    'debit' => $meta['base_amount'],
                    'credit' => 0.0,
                    ...$meta,
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
            ->with('sale')
            ->where('status', SalesReturnStatus::CONFIRMED->value)
            ->where('customer_id', $customerId)
            ->when($fromDate, fn ($query) => $query->whereDate('return_date', '>=', $fromDate))
            ->when($toDate, fn ($query) => $query->whereDate('return_date', '<=', $toDate))
            ->get()
            ->each(function (SalesReturn $return) use ($transactions) {
                $meta = $this->currencyMeta(
                    $return->currency_code ?: $return->sale?->currency_code,
                    $return->exchange_rate ?: $return->sale?->exchange_rate,
                    (float) $return->grand_total,
                );

                $transactions->push([
                    'date' => $return->return_date,
                    'reference' => $return->return_no,
                    'type' => 'sales_return',
                    'description' => "Sales Return {$return->return_no}",
                    'debit' => 0.0,
                    'credit' => $meta['base_amount'],
                    ...$meta,
                ]);
            });
    }

    private function loadPayments(
        Collection $transactions,
        int $customerId,
        ?string $fromDate,
        ?string $toDate,
        string $baseCurrency
    ): void {
        $this->confirmedPayments($customerId)
            ->when($fromDate, fn ($query) => $query->whereDate('payment_date', '>=', $fromDate))
            ->when($toDate, fn ($query) => $query->whereDate('payment_date', '<=', $toDate))
            ->get()
            ->each(function (Payment $payment) use ($transactions, $baseCurrency) {
                $amount = (float) $payment->amount;
                $isRefund = $payment->purpose === PaymentPurposeEnum::CUSTOMER_REFUND;

                $transactions->push([
                    'date' => $payment->payment_date,
                    'reference' => $payment->payment_no,
                    'type' => $isRefund ? 'refund' : 'receipt',
                    'description' => $isRefund
                        ? "Customer Refund {$payment->payment_no}"
                        : "Receipt {$payment->payment_no}",
                    'debit' => $isRefund ? $amount : 0.0,
                    'credit' => $isRefund ? 0.0 : $amount,
                    'currency_code' => $baseCurrency,
                    'exchange_rate' => 1.0,
                    'document_amount' => round($amount, 4),
                    'base_amount' => round($amount, 4),
                ]);
            });
    }

    private function confirmedPayments(int $customerId)
    {
        return Payment::query()
            ->where('party_type', PaymentPartyTypeEnum::CUSTOMER)
            ->where('party_id', $customerId)
            ->where('status', PaymentStatusEnum::CONFIRMED->value)
            ->whereIn('purpose', self::CUSTOMER_PAYMENT_PURPOSES);
    }

    private function allocatedAmountSince(
        string $documentType,
        int $documentId,
        ?string $fromDate
    ): float {
        return (float) PaymentAllocation::query()
            ->where('document_type', $documentType)
            ->where('document_id', $documentId)
            ->whereHas('payment', function ($query) use ($fromDate) {
                $query->where('status', PaymentStatusEnum::CONFIRMED->value)
                    ->when(
                        $fromDate,
                        fn ($paymentQuery) => $paymentQuery->whereDate('payment_date', '>=', $fromDate)
                    );
            })
            ->sum('allocated_amount');
    }

    private function currencyMeta(
        ?string $currencyCode,
        string|int|float|null $exchangeRate,
        float $documentAmount
    ): array {
        $rate = (float) ($exchangeRate ?: 1);

        return [
            'currency_code' => strtoupper(
                $currencyCode ?: $this->baseCurrencyCode()
            ),
            'exchange_rate' => $rate,
            'document_amount' => round($documentAmount, 4),
            'base_amount' => $this->baseAmount($documentAmount, $rate),
        ];
    }

    private function baseAmount(
        string|int|float $amount,
        string|int|float|null $exchangeRate
    ): float {
        return round(
            (float) $amount * (float) ($exchangeRate ?: 1),
            4
        );
    }

    private function baseCurrencyCode(): string
    {
        return strtoupper(trim((string) (
            Setting::query()
                ->where('group', Setting::GROUP_COMPANY)
                ->where('key', 'currency')
                ->value('value') ?? 'INR'
        )));
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
            $row['balance'] = round($balance, 4);

            return $row;
        });

        return round($balance, 4);
    }
}
