<?php

namespace App\Modules\Accounting\Repositories;

use Illuminate\Support\Collection;

use App\Core\Enums\OpeningBalanceTypeEnum;
use App\Modules\Accounting\Repositories\Contracts\SupplierStatementRepositoryInterface;
use App\Modules\Payment\Enums\PaymentPartyTypeEnum;
use App\Modules\Payment\Enums\PaymentPurposeEnum;
use App\Modules\Payment\Enums\PaymentStatusEnum;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Purchase\Enums\PurchaseStatusEnum;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnStatus;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;
use App\Modules\Settings\Models\Setting;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\SupplierOpeningBill\Models\SupplierOpeningBill;

class SupplierStatementRepository implements SupplierStatementRepositoryInterface
{
    private const OPENING_BILL_TYPE = 'supplier_opening_bill';

    private const SUPPLIER_PAYMENT_PURPOSES = [
        'supplier_payment',
        'supplier_advance',
        'supplier_refund',
    ];

    public function getStatement(
        int $supplierId,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $supplier = Supplier::query()->findOrFail($supplierId);
        $baseCurrency = $this->baseCurrencyCode();

        $openingBalance = $this->calculateOpeningBalance(
            $supplierId,
            $fromDate,
            $toDate,
        );

        $transactions = collect();

        $this->loadOpeningBills($transactions, $supplierId, $fromDate, $toDate, $baseCurrency);
        $this->loadPurchases($transactions, $supplierId, $fromDate, $toDate);
        $this->loadPurchaseReturns($transactions, $supplierId, $fromDate, $toDate);
        $this->loadPayments($transactions, $supplierId, $fromDate, $toDate, $baseCurrency);

        $transactions = $this->sortTransactions($transactions);

        $runningBalance = $this->applyRunningBalance(
            $transactions,
            $openingBalance
        );

        return [
            'supplier' => $supplier,
            'base_currency_code' => $baseCurrency,
            'opening_balance' => $openingBalance,
            'transactions' => $transactions,
            'outstanding_balance' => $runningBalance,
        ];
    }

    private function calculateOpeningBalance(
        int $supplierId,
        ?string $fromDate,
        ?string $toDate
    ): float {
        $openingBalance = 0.0;

        $openingBills = SupplierOpeningBill::query()
            ->where('supplier_id', $supplierId)
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
            // Add back allocations from the statement start onward because
            // those payments are shown separately in the statement period.
            $balanceAtStart = (float) $bill->balance_amount
                + $this->allocatedAmountSince(
                    self::OPENING_BILL_TYPE,
                    (int) $bill->getKey(),
                    $fromDate,
                );

            $openingBalance += $bill->balance_type === OpeningBalanceTypeEnum::CREDIT
                ? $balanceAtStart
                : -$balanceAtStart;
        }

        if ($fromDate === null) {
            return round($openingBalance, 4);
        }

        $priorPurchases = Purchase::query()
            ->where('supplier_id', $supplierId)
            ->whereIn('status', [
                PurchaseStatusEnum::CONFIRMED->value,
                PurchaseStatusEnum::PARTIALLY_PAID->value,
                PurchaseStatusEnum::PAID->value,
            ])
            ->whereDate('purchase_date', '<', $fromDate)
            ->get()
            ->sum(fn (Purchase $purchase) => $this->baseAmount(
                $purchase->grand_total,
                $purchase->exchange_rate,
            ));

        $priorReturns = PurchaseReturn::query()
            ->with('purchase')
            ->where('supplier_id', $supplierId)
            ->where('status', PurchaseReturnStatus::CONFIRMED->value)
            ->whereDate('return_date', '<', $fromDate)
            ->get()
            ->sum(fn (PurchaseReturn $return) => $this->baseAmount(
                $return->grand_total,
                $return->exchange_rate ?: $return->purchase?->exchange_rate,
            ));

        $priorPayments = $this->confirmedPayments($supplierId)
            ->whereDate('payment_date', '<', $fromDate)
            ->get();

        foreach ($priorPayments as $payment) {
            if ($payment->purpose === PaymentPurposeEnum::SUPPLIER_REFUND) {
                $openingBalance += (float) $payment->amount;
            } else {
                $openingBalance -= (float) $payment->amount;
            }
        }

        return round(
            $openingBalance + $priorPurchases - $priorReturns,
            4
        );
    }

    private function loadOpeningBills(
        Collection $transactions,
        int $supplierId,
        ?string $fromDate,
        ?string $toDate,
        string $baseCurrency
    ): void {
        if ($fromDate === null) {
            return;
        }

        SupplierOpeningBill::query()
            ->where('supplier_id', $supplierId)
            ->whereDate('bill_date', '>=', $fromDate)
            ->when($toDate, fn ($query) => $query->whereDate('bill_date', '<=', $toDate))
            ->get()
            ->each(function (SupplierOpeningBill $bill) use ($transactions, $baseCurrency) {
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

    private function loadPurchases(
        Collection $transactions,
        int $supplierId,
        ?string $fromDate,
        ?string $toDate
    ): void {
        Purchase::query()
            ->where('supplier_id', $supplierId)
            ->whereIn('status', [
                PurchaseStatusEnum::CONFIRMED->value,
                PurchaseStatusEnum::PARTIALLY_PAID->value,
                PurchaseStatusEnum::PAID->value,
            ])
            ->when($fromDate, fn ($query) => $query->whereDate('purchase_date', '>=', $fromDate))
            ->when($toDate, fn ($query) => $query->whereDate('purchase_date', '<=', $toDate))
            ->get()
            ->each(function (Purchase $purchase) use ($transactions) {
                $meta = $this->currencyMeta(
                    $purchase->currency_code,
                    $purchase->exchange_rate,
                    (float) $purchase->grand_total,
                );

                $transactions->push([
                    'date' => $purchase->purchase_date,
                    'reference' => $purchase->purchase_no,
                    'type' => 'purchase',
                    'description' => "Purchase {$purchase->purchase_no}",
                    'debit' => 0.0,
                    'credit' => $meta['base_amount'],
                    ...$meta,
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
            ->with('purchase')
            ->where('supplier_id', $supplierId)
            ->where('status', PurchaseReturnStatus::CONFIRMED->value)
            ->when($fromDate, fn ($query) => $query->whereDate('return_date', '>=', $fromDate))
            ->when($toDate, fn ($query) => $query->whereDate('return_date', '<=', $toDate))
            ->get()
            ->each(function (PurchaseReturn $return) use ($transactions) {
                $meta = $this->currencyMeta(
                    $return->currency_code ?: $return->purchase?->currency_code,
                    $return->exchange_rate ?: $return->purchase?->exchange_rate,
                    (float) $return->grand_total,
                );

                $transactions->push([
                    'date' => $return->return_date,
                    'reference' => $return->return_no,
                    'type' => 'purchase_return',
                    'description' => "Purchase Return {$return->return_no}",
                    'debit' => $meta['base_amount'],
                    'credit' => 0.0,
                    ...$meta,
                ]);
            });
    }

    private function loadPayments(
        Collection $transactions,
        int $supplierId,
        ?string $fromDate,
        ?string $toDate,
        string $baseCurrency
    ): void {
        $this->confirmedPayments($supplierId)
            ->when($fromDate, fn ($query) => $query->whereDate('payment_date', '>=', $fromDate))
            ->when($toDate, fn ($query) => $query->whereDate('payment_date', '<=', $toDate))
            ->get()
            ->each(function (Payment $payment) use ($transactions, $baseCurrency) {
                $amount = (float) $payment->amount;
                $isRefund = $payment->purpose === PaymentPurposeEnum::SUPPLIER_REFUND;

                $transactions->push([
                    'date' => $payment->payment_date,
                    'reference' => $payment->payment_no,
                    'type' => $isRefund ? 'refund' : 'payment',
                    'description' => $isRefund
                        ? "Supplier Refund {$payment->payment_no}"
                        : "Payment {$payment->payment_no}",
                    'debit' => $isRefund ? 0.0 : $amount,
                    'credit' => $isRefund ? $amount : 0.0,
                    'currency_code' => $baseCurrency,
                    'exchange_rate' => 1.0,
                    'document_amount' => round($amount, 4),
                    'base_amount' => round($amount, 4),
                ]);
            });
    }

    private function confirmedPayments(int $supplierId)
    {
        return Payment::query()
            ->where('party_type', PaymentPartyTypeEnum::SUPPLIER)
            ->where('party_id', $supplierId)
            ->where('status', PaymentStatusEnum::CONFIRMED->value)
            ->whereIn('purpose', self::SUPPLIER_PAYMENT_PURPOSES);
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
            $balance += $row['credit'];
            $balance -= $row['debit'];
            $row['balance'] = round($balance, 4);

            return $row;
        });

        return round($balance, 4);
    }
}
