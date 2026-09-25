<?php

namespace App\Modules\Accounting\Services;

use App\Core\Exceptions\BusinessException;

use App\Modules\Accounting\Enums\JournalEntryStatusEnum;

use App\Modules\Accounting\Models\JournalEntry;

use App\Modules\Accounting\Services\JournalEntry\JournalEntryService;

use App\Modules\Accounting\Repositories\Contracts\JournalEntryRepositoryInterface;

class AccountingReversalService
{
    public function __construct(
        protected JournalEntryRepositoryInterface $repository,
        protected JournalEntryService $journalEntryService,
    ) {}

    public function reverse(
        JournalEntry $journalEntry
    ): JournalEntry {

        if (
            $journalEntry->status
            !== JournalEntryStatusEnum::POSTED
        ) {
            throw new BusinessException(
                'Only posted journal entries can be reversed.'
            );
        }

        $journalEntry->loadMissing([
            'lines.account',
            'reversal',
        ]);

        if ($journalEntry->reversal) {
            throw new BusinessException(
                'Journal entry has already been reversed.'
            );
        }

        if ($journalEntry->lines->isEmpty()) {
            throw new BusinessException(
                'Journal entry has no lines.'
            );
        }

        $lines = [];

        foreach ($journalEntry->lines as $line) {
            if (! $line->account) {
                throw new BusinessException(
                    'Journal entry account not found.'
                );
            }

            $lines[] = [
                'account_code' => $line->account->account_code,
                'debit' => (string) $line->credit,
                'credit' => (string) $line->debit,
                'description' => $line->description,
            ];
        }

        return $this->journalEntryService->createAndPost([
            'voucher_type' =>
                $journalEntry->voucher_type,
            'reference_type' =>
                $journalEntry->reference_type,
            'reference_id' =>
                $journalEntry->reference_id,
            'entry_date' =>
                $journalEntry->entry_date,
            'description' =>
                'Reversal of ' . $journalEntry->voucher_no,
            'status' => 'draft',
            'created_by' => auth()->id(),
            'reversal_of_journal_entry_id' =>
                $journalEntry->id,
            'lines' => $lines,
        ]);
    }

    public function reverseByReference(
        string $referenceType,
        int $referenceId,
        string $voucherType
    ): JournalEntry {
        $journalEntry =
            $this->repository->findPostedByReference(
                referenceType: $referenceType,
                referenceId: $referenceId,
                voucherType: $voucherType,
            );

        if (! $journalEntry) {
            throw new BusinessException(
                'Original journal entry not found.'
            );
        }

        return $this->reverse($journalEntry);
    }
}