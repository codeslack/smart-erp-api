<?php

// app/Modules/SupplierOpeningBill/Services/SupplierOpeningBillPostingService.php

namespace App\Modules\SupplierOpeningBill\Services;

use Illuminate\Support\Facades\DB;

use App\Modules\Supplier\Models\Supplier;

use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;

use App\Modules\Accounting\Services\AccountingPostingService;
use App\Modules\Accounting\Services\AccountingReversalService;

use App\Modules\Accounting\Repositories\Contracts\JournalEntryRepositoryInterface;

class SupplierOpeningBillPostingService
{
    public function __construct(
        protected AccountingPostingService $accountingPosting,
        protected AccountingReversalService $accountingReversal,
        protected JournalEntryRepositoryInterface $journalEntries,
        protected SupplierOpeningBillService $openingBills,
    ) {}

    /**
     * Post the current supplier opening-bill balance.
     */
    public function post(
        Supplier $supplier
    ): void {
        $openingBills =
            $this->openingBills
                ->findBySupplier(
                    $supplier->id
                );

        if ($openingBills->isEmpty()) {
            return;
        }

        $this->accountingPosting
            ->postSupplierOpeningBalance(
                $supplier,
                $openingBills
            );
    }

    /**
     * Reverse the active supplier opening-balance journal.
     */
    public function reverse(
        Supplier $supplier
    ): void {
        $journalEntry =
            $this->findActiveJournal(
                $supplier
            );

        if (! $journalEntry) {
            return;
        }

        $this->accountingReversal
            ->reverse(
                $journalEntry
            );
    }

    /**
     * Reverse the existing opening-balance journal
     * and post the current opening-bill balance.
     */
    public function repost(
        Supplier $supplier
    ): void {
        DB::transaction(
            function () use ($supplier) {

                $this->reverse(
                    $supplier
                );

                $this->post(
                    $supplier
                );
            }
        );
    }

    protected function findActiveJournal(
        Supplier $supplier
    ) {
        return $this->journalEntries
            ->findPostedByReference(
                referenceType:
                    Supplier::class,

                referenceId:
                    $supplier->id,

                voucherType:
                    JournalVoucherTypeEnum::SUPPLIER_OPENING_BALANCE->value,
            );
    }
}