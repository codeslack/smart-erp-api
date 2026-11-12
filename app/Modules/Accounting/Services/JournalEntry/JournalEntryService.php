<?php

namespace App\Modules\Accounting\Services\JournalEntry;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

use App\Core\Exceptions\BusinessException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Repositories\Contracts\JournalEntryRepositoryInterface;

class JournalEntryService
{
    public function __construct(
        protected JournalEntryRepositoryInterface $repository,
        protected JournalEntryCreator $creator,
        protected JournalEntryPoster $poster,
        protected JournalEntryCanceller $canceller,
    ) {}

    public function getAll(): LengthAwarePaginator
    {
        return $this->repository->paginate();
    }

    public function find(
        int|string $id
    ): JournalEntry {
        return $this->repository->findById($id);
    }

    public function create(
        array $data
    ): JournalEntry {
        return DB::transaction(
            fn () => $this->creator->create($data)
        );
    }

    public function createAndPost(
        array $data
    ): JournalEntry {
        return DB::transaction(
            function () use ($data) {
                $postingKey = $data['posting_key'] ?? null;
                $referenceType = $data['reference_type'] ?? null;
                $referenceId = isset($data['reference_id'])
                    ? (int) $data['reference_id']
                    : null;
                $isReversal = ! empty($data['reversal_of_journal_entry_id']);

                if (
                    ! $isReversal
                    && is_string($postingKey)
                    && $postingKey !== ''
                ) {
                    $this->lockPostingReference(
                        $referenceType,
                        $referenceId
                    );

                    $existing = $this->repository
                        ->findByPostingKey($postingKey);

                    if ($existing) {
                        return $existing;
                    }
                }

                $journalEntry = $this->creator->create(
                    $data
                );

                return $this->poster->post(
                    $journalEntry
                );
            }
        );
    }

    public function post(
        JournalEntry $journalEntry
    ): JournalEntry {
        return DB::transaction(
            fn () => $this->poster->post(
                $journalEntry
            )
        );
    }

    public function cancel(
        JournalEntry $journalEntry
    ): JournalEntry {
        return DB::transaction(
            fn () => $this->canceller->cancel(
                $journalEntry
            )
        );
    }
}