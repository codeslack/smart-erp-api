<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Inventory\Models\StockLedger;
use App\Modules\Purchase\Enums\PurchaseStatus;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\SalesReturn\Enums\SalesReturnStatus;
use App\Modules\CustomerReceipt\Enums\CustomerReceiptStatus;
use App\Modules\SupplierPayment\Enums\SupplierPaymentStatus;
use App\Modules\Accounting\Services\Contracts\AccountingPostingServiceInterface;

class AccountingPostingService
    implements AccountingPostingServiceInterface
{
    public function __construct(
        protected JournalEntryService $journalEntryService
    ) {}

    public function postSale(
        mixed $sale
    ) {

        abort_if(
            $sale->status !== SaleStatus::CONFIRMED,
            422,
            'Only confirmed sales can be posted.'
        );

        $this->ensureJournalNotExists(
            $sale
        );

        $cogsAmount = 0;

        $cogsAmount = StockLedger::query()
            ->where('reference_type', get_class($sale))
            ->where('reference_id', $sale->id)
            ->sum('line_cost');

        $journal = $this->journalEntryService
            ->create([

                'voucher_type'
                    => 'sale',

                'reference_type'
                    => get_class(
                        $sale
                    ),

                'reference_id'
                    => $sale->id,

                'entry_date'
                    => $sale->sale_date,

                'description'
                    => 'Sale '
                    . $sale->sale_no,

                'lines' => [

                    [
                        'chart_of_account_id' => $this->accountId('1100'),
                        'debit' => $sale->grand_total,
                    ],

                    [
                        'chart_of_account_id' => $this->accountId('4000'),
                        'credit' => $sale->grand_total,
                    ],

                    [
                        'chart_of_account_id' => $this->accountId('5000'),
                        'debit' => $cogsAmount,
                    ],

                    [
                        'chart_of_account_id' => $this->accountId('1200'),
                        'credit' => $cogsAmount,
                    ],
                ],

            ]);

        return $this->journalEntryService
            ->post(
                $journal
            );
    }

    public function postPurchase(
        mixed $purchase
    ) {

        abort_if(
            $purchase->status !== PurchaseStatus::CONFIRMED,
            422,
            'Only confirmed purchases can be posted.'
        );

        $this->ensureJournalNotExists(
            $purchase
        );

        $journal = $this->journalEntryService
            ->create([

                'voucher_type'
                    => 'purchase',

                'reference_type'
                    => get_class(
                        $purchase
                    ),

                'reference_id'
                    => $purchase->id,

                'entry_date'
                    => $purchase->purchase_date,

                'description'
                    => 'Purchase '
                    . $purchase->purchase_no,

                'lines' => [

                    [

                        'chart_of_account_id'
                            => $this->accountId(
                                '1200'
                            ),

                        'debit'
                            => $purchase->grand_total,
                    ],

                    [

                        'chart_of_account_id'
                            => $this->accountId(
                                '2000'
                            ),

                        'credit'
                            => $purchase->grand_total,
                    ],
                ],
            ]);

        return $this->journalEntryService
            ->post(
                $journal
            );
    }

    public function postCustomerReceipt(
        mixed $receipt
    ) {

        abort_if(
            $receipt->status !== CustomerReceiptStatus::CONFIRMED,
            422,
            'Only confirmed receipts can be posted.'
        );

        $this->ensureJournalNotExists(
            $receipt
        );

        $journal = $this->journalEntryService
            ->create([

                'voucher_type'
                    => 'customer_receipt',

                'reference_type'
                    => get_class(
                        $receipt
                    ),

                'reference_id'
                    => $receipt->id,

                'entry_date'
                    => $receipt->receipt_date,

                'description'
                    => 'Customer Receipt '
                    . $receipt->receipt_no,

                'lines' => [

                    [

                        'chart_of_account_id'
                            => $this->accountId(
                                '1000'
                            ),

                        'debit'
                            => $receipt->amount,
                    ],

                    [

                        'chart_of_account_id'
                            => $this->accountId(
                                '1100'
                            ),

                        'credit'
                            => $receipt->amount,
                    ],
                ],
            ]);

        return $this->journalEntryService
            ->post(
                $journal
            );
    }

    public function postSupplierPayment(
        mixed $payment
    ) {

        abort_if(
            $payment->status !== SupplierPaymentStatus::CONFIRMED,
            422,
            'Only confirmed payments can be posted.'
        );

        $this->ensureJournalNotExists(
            $payment
        );

        $journal = $this->journalEntryService
            ->create([

                'voucher_type'
                    => 'supplier_payment',

                'reference_type'
                    => get_class(
                        $payment
                    ),

                'reference_id'
                    => $payment->id,

                'entry_date'
                    => $payment->payment_date,

                'description'
                    => 'Supplier Payment '
                    . $payment->payment_no,

                'lines' => [

                    [

                        'chart_of_account_id'
                            => $this->accountId(
                                '2000'
                            ),

                        'debit'
                            => $payment->amount,
                    ],

                    [

                        'chart_of_account_id'
                            => $this->accountId(
                                '1000'
                            ),

                        'credit'
                            => $payment->amount,
                    ],
                ],
            ]);

        return $this->journalEntryService
            ->post(
                $journal
            );
    }

    public function postPurchaseReturn(
        mixed $purchaseReturn
    )
    {
        $this->ensureJournalNotExists(
            $purchaseReturn
        );

        $journal = $this->journalEntryService
            ->create([

                'voucher_type'
                    => 'purchase_return',

                'reference_type'
                    => get_class(
                        $purchaseReturn
                    ),

                'reference_id'
                    => $purchaseReturn->id,

                'entry_date'
                    => $purchaseReturn->return_date,

                'description'
                    => 'Purchase Return '
                    . $purchaseReturn->return_no,

                'lines' => [

                    [
                        'chart_of_account_id'
                            => $this->accountId('2000'),

                        'debit'
                            => $purchaseReturn->grand_total,
                    ],

                    [
                        'chart_of_account_id'
                            => $this->accountId('1200'),

                        'credit'
                            => $purchaseReturn->grand_total,
                    ],
                ],
            ]);

        return $this->journalEntryService
            ->post($journal);
    }

    public function postSalesReturn(
        mixed $salesReturn
    ) {

        abort_if(
            $salesReturn->status !== SalesReturnStatus::CONFIRMED,
            422,
            'Only confirmed sales returns can be posted.'
        );

        $this->ensureJournalNotExists(
            $salesReturn
        );

        $inventoryAmount = 0;

        foreach (
            $salesReturn->items as $item
        ) {

            $stockLedger = StockLedger::query()

                ->where(
                    'reference_type',
                    get_class($salesReturn)
                )

                ->where(
                    'reference_id',
                    $salesReturn->id
                )

                ->where(
                    'product_id',
                    $item->product_id
                )

                ->latest('id')

                ->first();

            $inventoryAmount +=
                $stockLedger?->line_cost ?? 0;
        }

        $journal = $this->journalEntryService
            ->create([

                'voucher_type'
                    => 'sales_return',

                'reference_type'
                    => get_class(
                        $salesReturn
                    ),

                'reference_id'
                    => $salesReturn->id,

                'entry_date'
                    => $salesReturn->return_date,

                'description'
                    => 'Sales Return '
                    . $salesReturn->return_no,

                'lines' => [

                    [
                        'chart_of_account_id'
                            => $this->accountId(
                                '4100'
                            ),

                        'debit'
                            => $salesReturn->grand_total,
                    ],

                    [
                        'chart_of_account_id'
                            => $this->accountId(
                                '1100'
                            ),

                        'credit'
                            => $salesReturn->grand_total,
                    ],

                    [
                        'chart_of_account_id'
                            => $this->accountId(
                                '1200'
                            ),

                        'debit'
                            => $inventoryAmount,
                    ],

                    [
                        'chart_of_account_id'
                            => $this->accountId(
                                '5000'
                            ),

                        'credit'
                            => $inventoryAmount,
                    ],
                ],
            ]);

        return $this->journalEntryService
            ->post($journal);
    }

    protected function accountId(
        string $code
    ): int {

        return ChartOfAccount::query()

            ->where(
                'tenant_id',
                tenant()->id
            )

            ->where(
                'account_code',
                $code
            )

            ->firstOrFail()

            ->id;
    }

    protected function ensureJournalNotExists(
        mixed $reference
    ): void {

        $exists = JournalEntry::query()

            ->where(
                'tenant_id',
                $reference->tenant_id
            )

            ->where(
                'reference_type',
                get_class($reference)
            )

            ->where(
                'reference_id',
                $reference->id
            )

            ->exists();

        abort_if(
            $exists,
            422,
            'Journal already exists for this document.'
        );
    }

}
