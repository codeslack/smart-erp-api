<?php

namespace App\Modules\CustomerOpeningBill\Services;

use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;
use App\Modules\Accounting\Repositories\Contracts\JournalEntryRepositoryInterface;
use App\Modules\Accounting\Services\AccountingPostingService;
use App\Modules\Accounting\Services\AccountingReversalService;
use App\Modules\Customer\Models\Customer;

class CustomerOpeningBillPostingService
{
    public function __construct(
        protected AccountingPostingService $accountingPosting,
        protected AccountingReversalService $accountingReversal,
        protected JournalEntryRepositoryInterface $journalEntries,
        protected CustomerOpeningBillService $openingBills,
    ) {}

    /**
     * Post the current opening-bill balance
     * for the customer.
     */
    public function post(
        Customer $customer
    ): void {
        $openingBills =
            $this->openingBills
                ->findByCustomer(
                    $customer->id
                );

        if ($openingBills->isEmpty()) {
            return;
        }

        $this->accountingPosting
            ->postCustomerOpeningBalance(
                $customer,
                $openingBills
            );
    }

    /**
     * Reverse the active customer opening-balance
     * journal.
     */
    public function reverse(
        Customer $customer
    ): void {
        $journalEntry =
            $this->findActiveJournal(
                $customer
            );

        if (!$journalEntry) {
            return;
        }

        $this->accountingReversal
            ->reverse(
                $journalEntry
            );
    }

    /**
     * Reverse the old opening balance and post
     * the current opening-bill balance again.
     */
    public function repost(
        Customer $customer
    ): void {
        $this->reverse(
            $customer
        );

        $this->post(
            $customer
        );
    }

    protected function findActiveJournal(
        Customer $customer
    ) {
        return $this->journalEntries
            ->findPostedByReference(
                referenceType:
                    Customer::class,

                referenceId:
                    $customer->id,

                voucherType:
                    JournalVoucherTypeEnum::CUSTOMER_OPENING_BALANCE->value,
            );
    }
}
