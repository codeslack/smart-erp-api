<?php

namespace App\Modules\Accounting\Services\Postings;

use App\Modules\Accounting\Services\JournalEntryService;

abstract class BasePostingService
{
    public function __construct(
        protected JournalEntryService $journalEntryService
    ) {}

    protected function createJournalEntry(
        string $entryDate,
        string $voucherType,
        string $referenceType,
        int $referenceId,
        string $description,
        array $lines
    ): void {

        $this->journalEntryService
            ->createAndPost([

                'voucher_type' => $voucherType,

                'reference_type' => $referenceType,

                'reference_id' => $referenceId,

                'entry_date' => $entryDate,

                'description' => $description,

                'status' => 'draft',

                'created_by' => auth()->id(),

                'lines' => $lines,
            ]);
    }
}