<?php

namespace App\Modules\SalesReturn\Services\Posting;

use App\Modules\Accounting\Enums\JournalEntryStatusEnum;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\AccountingReversalService;
use App\Modules\Accounting\Services\Postings\SalesReturnPostingService;
use App\Modules\SalesReturn\Models\SalesReturn;
use Throwable;

class SalesReturnAccountingPostingService
{
    public function __construct(
        protected SalesReturnPostingService $salesReturnPostingService,
        protected AccountingReversalService $accountingReversalService
    ) {}

    public function post(SalesReturn $salesReturn): void
    {
        try {
            $this->salesReturnPostingService->post($salesReturn);
        } catch (Throwable $e) {
            throw $e;
        }
    }

    public function reverse(SalesReturn $salesReturn): void
    {
        $entries = JournalEntry::query()
            ->where('tenant_id', tenantId())
            ->where('reference_type', SalesReturn::class)
            ->where('reference_id', $salesReturn->id)
            ->whereNull('reversal_of_journal_entry_id')
            ->where('status', JournalEntryStatusEnum::POSTED->value)
            ->whereDoesntHave('reversal')
            ->orderBy('id')
            ->get();

        foreach ($entries as $entry) {
            $this->accountingReversalService->reverse($entry);
        }
    }
}
