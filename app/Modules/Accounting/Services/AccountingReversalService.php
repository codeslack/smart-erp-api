<?php

namespace App\Modules\Accounting\Services;

use App\Core\Exceptions\BusinessException;
use App\Modules\Accounting\Enums\JournalEntryStatusEnum;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\JournalEntry\JournalEntryService;
use App\Modules\Accounting\Repositories\Contracts\JournalEntryRepositoryInterface;
use Illuminate\Support\Facades\DB;

class AccountingReversalService
{
    public function __construct(
        protected JournalEntryRepositoryInterface $repository,
        protected JournalEntryService $journalEntryService,
    ) {}

    public function reverse(JournalEntry $journalEntry): JournalEntry
    {
        return DB::transaction(function () use ($journalEntry) {
            // Serialize reversal attempts for this exact journal within its tenant.
            $original = JournalEntry::query()
                ->where('tenant_id', tenantId())
                ->whereKey($journalEntry->id)
                ->lockForUpdate()
                ->first();

            if (! $original) {
                throw new BusinessException('Original journal entry not found in the active tenant.');
            }

            if ($original->status !== JournalEntryStatusEnum::POSTED) {
                throw new BusinessException('Only posted journal entries can be reversed.');
            }

            $original->load(['lines.account', 'reversal']);

            if ($original->reversal) {
                throw new BusinessException('Journal entry has already been reversed.');
            }

            if ($original->lines->isEmpty()) {
                throw new BusinessException('Journal entry has no lines.');
            }

            $lines = [];

            foreach ($original->lines as $line) {
                if (! $line->account) {
                    throw new BusinessException('Journal entry account not found.');
                }

                if ((int) $line->account->tenant_id !== (int) $original->tenant_id) {
                    throw new BusinessException('Journal entry contains an account from another tenant.');
                }

                $lines[] = [
                    'chart_of_account_uuid' => $line->account->uuid,
                    'debit' => (string) $line->credit,
                    'credit' => (string) $line->debit,
                    'foreign_debit' => $line->foreign_credit,
                    'foreign_credit' => $line->foreign_debit,
                    'description' => $line->description,
                ];
            }

            return $this->journalEntryService->createAndPost([
                'voucher_type' => $original->voucher_type,
                'reference_type' => $original->reference_type,
                'reference_id' => $original->reference_id,
                'entry_date' => $original->entry_date,
                'currency_code' => $original->currency_code,
                'exchange_rate' => $original->exchange_rate,
                'description' => 'Reversal of ' . $original->voucher_no,
                'status' => JournalEntryStatusEnum::DRAFT,
                'created_by' => auth()->id(),
                'reversal_of_journal_entry_id' => $original->id,
                'lines' => $lines,
            ]);
        });
    }

    public function reverseByReference(
        string $referenceType,
        int $referenceId,
        string $voucherType
    ): JournalEntry {
        $journalEntry = $this->repository->findPostedByReference(
            referenceType: $referenceType,
            referenceId: $referenceId,
            voucherType: $voucherType,
        );

        if (! $journalEntry) {
            throw new BusinessException('Original journal entry not found.');
        }

        return $this->reverse($journalEntry);
    }
}
