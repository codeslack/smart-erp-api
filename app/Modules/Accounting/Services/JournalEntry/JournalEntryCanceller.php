<?php

namespace App\Modules\Accounting\Services\JournalEntry;

use App\Core\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;

use App\Modules\Accounting\Enums\JournalEntryStatusEnum;
use App\Modules\Accounting\Models\JournalEntry;

class JournalEntryCanceller
{
    public function cancel(
        JournalEntry $journalEntry
    ): JournalEntry {
        return DB::transaction(
            function () use ($journalEntry) {

                $journalEntry = JournalEntry::query()
                    ->whereKey($journalEntry->id)
                    ->lockForUpdate()
                    ->first();

                if (! $journalEntry) {
                    throw new BusinessException(
                        'Journal entry not found.'
                    );
                }

                if (
                    $journalEntry->status
                    === JournalEntryStatusEnum::POSTED
                ) {
                    throw new BusinessException(
                        'Posted journal entries cannot be cancelled.'
                    );
                }

                if (
                    $journalEntry->status
                    === JournalEntryStatusEnum::CANCELLED
                ) {
                    throw new BusinessException(
                        'Journal entry is already cancelled.'
                    );
                }

                if (
                    $journalEntry->status
                    !== JournalEntryStatusEnum::DRAFT
                ) {
                    throw new BusinessException(
                        'Only draft journal entries can be cancelled.'
                    );
                }

                $journalEntry->update([
                    'status' => JournalEntryStatusEnum::CANCELLED,
                ]);

                return $journalEntry->fresh();
            }
        );
    }
}